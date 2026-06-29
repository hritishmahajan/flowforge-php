<?php
declare(strict_types=1);

namespace FlowForge;

/**
 * The automation engine — the heart of the integration layer.
 *
 * An incoming event (e.g. a webhook from Stripe, a CRM, a form) is matched
 * against user-defined Workflows. Each workflow is:
 *     trigger    — the event type it listens for ("payment.succeeded")
 *     conditions — field/op/value rules evaluated against the event payload
 *     actions    — transforms + dispatches (HTTP webhook, tag, log)
 *
 * This is exactly the "customised integration to automate workflows" task
 * from the role, expressed as a small, dependency-free PHP core.
 */
final class Engine
{
    public function __construct(private Storage $store) {}

    /** Ingest one event, run it through every matching workflow, log the runs. */
    public function ingest(array $event): array
    {
        $type = (string)($event['type'] ?? '');
        $data = (array)($event['data'] ?? []);
        $results = [];

        foreach ($this->store->all('workflows') as $wf) {
            if (($wf['trigger'] ?? null) !== $type) {
                continue;
            }
            if (!$this->matches($wf['conditions'] ?? [], $data)) {
                continue;
            }
            $actions = $this->runActions($wf['actions'] ?? [], $data);
            $run = $this->store->insert('runs', [
                'workflow' => $wf['name'] ?? $wf['id'],
                'event_type' => $type,
                'input' => $data,
                'actions' => $actions,
                'status' => 'success',
            ]);
            $results[] = $run;
        }

        return [
            'event' => $type,
            'matched' => count($results),
            'runs' => $results,
        ];
    }

    /** Evaluate ALL conditions (AND). Supported ops: eq, neq, gt, lt, contains. */
    private function matches(array $conditions, array $data): bool
    {
        foreach ($conditions as $c) {
            $actual = $data[$c['field']] ?? null;
            $expected = $c['value'] ?? null;
            $ok = match ($c['op'] ?? 'eq') {
                'eq'       => $actual == $expected,
                'neq'      => $actual != $expected,
                'gt'       => (float)$actual > (float)$expected,
                'lt'       => (float)$actual < (float)$expected,
                'contains' => str_contains((string)$actual, (string)$expected),
                default    => false,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** Execute each action, returning a per-action log for the dashboard. */
    private function runActions(array $actions, array $data): array
    {
        $log = [];
        foreach ($actions as $a) {
            switch ($a['type'] ?? '') {
                case 'transform':
                    // Add/override a field — basic enrichment/mapping.
                    $data[$a['field']] = $a['value'];
                    $log[] = ['type' => 'transform', 'set' => $a['field'], 'to' => $a['value']];
                    break;

                case 'webhook':
                    // Dispatch the (possibly transformed) payload downstream.
                    $log[] = $this->dispatch((string)$a['url'], $data);
                    break;

                case 'tag':
                    $log[] = ['type' => 'tag', 'label' => $a['label'] ?? ''];
                    break;

                default:
                    $log[] = ['type' => 'noop'];
            }
        }
        return $log;
    }

    /** Fire an outbound POST. Demonstrates PHP as integration glue between APIs. */
    private function dispatch(string $url, array $payload): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['type' => 'webhook', 'url' => $url, 'sent' => false, 'error' => 'invalid url'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['type' => 'webhook', 'url' => $url, 'sent' => $err === '', 'http' => $code];
    }
}
