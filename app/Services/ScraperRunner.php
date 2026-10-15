<?php

namespace App\Services;

use App\Models\Execution;
use App\Models\HistoryValue;
use App\Models\ScrapingElement;
use App\Models\ScrapingTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class ScraperRunner
{
    public function run(ScrapingTask $task, ?string $scheduledAt = null): Execution
    {
        $fields = $task->project->elements()->where('is_active', true)->get();
        $execution = Execution::create([
            'id' => (string) Str::uuid(),
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'scheduled_at' => $scheduledAt ?? now(),
            'started_at' => now(),
            'status' => 'running',
        ]);
        $started = microtime(true);
        $values = [];
        $succeeded = 0;
        $failed = 0;

        try {
            if ($fields->isEmpty()) throw new \RuntimeException('No hay campos activos configurados.');
            $url = $task->project->url;
            $this->assertPublicUrl($url);
            $response = Http::timeout(25)->withOptions(['allow_redirects' => false])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; WebScraper/1.0)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->get($url);
            $response->throw();
            $crawler = new Crawler($response->body(), $url);

            foreach ($fields as $field) {
                try {
                    $nodes = $crawler->filter($field->selector_css);
                    $node = $nodes->first();
                    $raw = $nodes->count() ? $this->extractValue($node, $field) : '';
                    $status = trim($raw) === '' ? 'empty' : 'valid';
                    $error = null;
                } catch (Throwable $exception) {
                    $raw = '';
                    $status = 'invalid';
                    $error = $exception->getMessage();
                }
                $parsed = $this->normalize($raw, $field->data_type, $url);
                $values[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $task->project_id,
                    'execution_id' => $execution->id,
                    'element_id' => $field->id,
                    'element_name' => $field->name,
                    'data_type' => $field->data_type,
                    'raw_value' => $raw,
                    'normalized_value' => $parsed['normalized'],
                    'numeric_value' => $parsed['number'],
                    'currency' => $parsed['currency'],
                    'status' => $status,
                    'captured_at' => now(),
                    'error_message' => $error,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $status === 'valid' ? $succeeded++ : $failed++;
            }
            $errorMessage = $failed ? "$failed de {$fields->count()} campos no se pudieron extraer." : null;
        } catch (Throwable $exception) {
            $errorMessage = $exception->getMessage();
            foreach ($fields as $field) {
                $values[] = [
                    'id' => (string) Str::uuid(), 'project_id' => $task->project_id,
                    'execution_id' => $execution->id, 'element_id' => $field->id,
                    'element_name' => $field->name, 'data_type' => $field->data_type,
                    'raw_value' => '', 'normalized_value' => null, 'numeric_value' => null,
                    'currency' => null, 'status' => 'invalid', 'captured_at' => now(),
                    'error_message' => $errorMessage, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            $failed = $fields->count();
        }

        $processed = count($values);
        $status = $failed === 0 && $processed > 0 ? 'success' : ($succeeded > 0 ? 'partial' : 'failed');
        DB::transaction(function () use ($values, $execution, $task, $started, $processed, $succeeded, $failed, $status, $errorMessage) {
            foreach (array_chunk($values, 200) as $chunk) HistoryValue::insert($chunk);
            $execution->update([
                'finished_at' => now(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'status' => $status, 'fields_processed' => $processed,
                'fields_succeeded' => $succeeded, 'fields_failed' => $failed,
                'error_message' => $errorMessage,
            ]);
            $task->update([
                'last_run_at' => now(),
                'next_run_at' => $task->is_active ? now()->add($task->interval_unit, $task->interval_value) : null,
            ]);
        });
        return $execution->fresh();
    }

    private function extractValue(Crawler $node, ScrapingElement $field): string
    {
        $element = $node->getNode(0);
        if (!$element) return '';
        $method = $field->extraction_method;
        $attribute = $field->attribute_name;
        if (in_array($method, ['attribute', 'meta_attribute'], true) && $attribute) {
            return trim((string) $node->attr($attribute));
        }
        if ($method === 'input_value') return trim((string) $node->attr('value'));
        return trim($node->text('', true));
    }

    private function normalize(string $raw, string $type, string $url): array
    {
        $number = null;
        $currency = null;
        if ($type === 'price') {
            if (preg_match('/\b(USD|MXN|EUR|GBP|CAD|AUD|JPY)\b/i', $raw, $match)) $currency = strtoupper($match[1]);
            elseif (str_contains(strtolower($url), '.mx')) $currency = 'MXN';
            elseif (str_contains(strtolower($url), '.uk')) $currency = 'GBP';
            $numeric = preg_replace('/[^0-9,.-]/u', '', $raw) ?? '';
            if (str_contains($numeric, ',') && str_contains($numeric, '.')) {
                $numeric = strrpos($numeric, ',') > strrpos($numeric, '.') ? str_replace(',', '.', str_replace('.', '', $numeric)) : str_replace(',', '', $numeric);
            } elseif (substr_count($numeric, ',') === 1 && !str_contains($numeric, '.')) {
                $numeric = str_replace(',', '.', $numeric);
            } else {
                $numeric = str_replace(',', '', $numeric);
            }
            if (is_numeric($numeric)) $number = (float) $numeric;
        } elseif (in_array($type, ['number', 'percentage', 'rating'], true)) {
            $numeric = str_replace(',', '.', preg_replace('/[^0-9,.-]/u', '', $raw) ?? '');
            if (is_numeric($numeric)) $number = (float) $numeric;
        }
        return ['normalized' => trim($raw) === '' ? null : trim($raw), 'number' => $number, 'currency' => $currency];
    }

    private function assertPublicUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            throw new \RuntimeException('La URL no puede apuntar a un host local.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_column(dns_get_record($host, DNS_A | DNS_AAAA) ?: [], 'ip');
        if (!$addresses) $addresses = [gethostbyname($host)];
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('La URL resuelve a una dirección IP privada o reservada.');
            }
        }
    }
}