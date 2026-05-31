<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability\Prometheus;

final class PrometheusRegistry
{
    /** @var array<string, array<string, float>> */
    private array $counters = [];

    /** @var array<string, array<string, float>> */
    private array $gauges = [];

    /** @var array<string, array<string, array{values: list<float>, buckets: list<float>}>> */
    private array $histograms = [];

    /**
     * @param array<string, string> $labels
     */
    public function incrementCounter(string $name, array $labels = [], float $value = 1): void
    {
        $key = $this->labelKey($labels);
        $this->counters[$name][$key] = ($this->counters[$name][$key] ?? 0) + $value;
    }

    /**
     * @param array<string, string> $labels
     */
    public function setGauge(string $name, array $labels, float $value): void
    {
        $key = $this->labelKey($labels);
        $this->gauges[$name][$key] = $value;
    }

    /**
     * @param array<string, string> $labels
     * @param list<float> $buckets
     */
    public function observeHistogram(string $name, float $value, array $labels = [], array $buckets = []): void
    {
        $key = $this->labelKey($labels);
        $this->histograms[$name][$key]['values'][] = $value;
        $this->histograms[$name][$key]['buckets'] = $buckets !== [] ? $buckets : $this->defaultBuckets();
    }

    public function render(): string
    {
        $lines = [];

        foreach ($this->counters as $name => $series) {
            $lines[] = "# HELP {$name} Counter metric.";
            $lines[] = "# TYPE {$name} counter";

            foreach ($series as $labelKey => $value) {
                $lines[] = sprintf('%s%s %s', $name, $labelKey, $this->formatValue($value));
            }

            $lines[] = '';
        }

        foreach ($this->gauges as $name => $series) {
            $lines[] = "# HELP {$name} Gauge metric.";
            $lines[] = "# TYPE {$name} gauge";

            foreach ($series as $labelKey => $value) {
                $lines[] = sprintf('%s%s %s', $name, $labelKey, $this->formatValue($value));
            }

            $lines[] = '';
        }

        foreach ($this->histograms as $name => $series) {
            $lines[] = "# HELP {$name} Histogram metric.";
            $lines[] = "# TYPE {$name} histogram";

            foreach ($series as $labelKey => $data) {
                $buckets = $data['buckets'];
                $values = $data['values'];
                $baseLabels = $this->parseLabelKey($labelKey);

                foreach ($buckets as $bucket) {
                    $count = count(array_filter($values, static fn (float $value): bool => $value <= $bucket));
                    $labels = array_merge($baseLabels, ['le' => $this->formatBucket($bucket)]);
                    $lines[] = sprintf('%s_bucket%s %d', $name, $this->formatLabels($labels), $count);
                }

                $labels = array_merge($baseLabels, ['le' => '+Inf']);
                $lines[] = sprintf('%s_bucket%s %d', $name, $this->formatLabels($labels), count($values));
                $lines[] = sprintf('%s_sum%s %s', $name, $labelKey, $this->formatValue(array_sum($values)));
                $lines[] = sprintf('%s_count%s %d', $name, $labelKey, count($values));
            }

            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * @param array<string, string> $labels
     */
    private function labelKey(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        ksort($labels);

        return $this->formatLabels($labels);
    }

    /**
     * @param array<string, string> $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $parts = [];

        foreach ($labels as $name => $value) {
            $parts[] = sprintf('%s="%s"', $name, $this->escapeLabelValue($value));
        }

        return '{'.implode(',', $parts).'}';
    }

    /**
     * @return array<string, string>
     */
    private function parseLabelKey(string $labelKey): array
    {
        if ($labelKey === '' || ! str_starts_with($labelKey, '{')) {
            return [];
        }

        $labels = [];
        $content = trim($labelKey, '{}');
        $pairs = explode(',', $content);

        foreach ($pairs as $pair) {
            if ($pair === '' || ! str_contains($pair, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $pair, 2);
            $labels[$name] = trim($value, '"');
        }

        return $labels;
    }

    private function escapeLabelValue(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }

    private function formatValue(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    private function formatBucket(float $bucket): string
    {
        return $this->formatValue($bucket);
    }

    /**
     * @return list<float>
     */
    private function defaultBuckets(): array
    {
        return [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];
    }
}
