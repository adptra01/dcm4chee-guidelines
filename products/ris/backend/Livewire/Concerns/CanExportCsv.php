<?php

declare(strict_types=1);

namespace Src\Components\Livewire\Concerns;

trait CanExportCsv
{
    /**
     * Generates a CSV string from the given models.
     *
     * @param  \Illuminate\Support\Collection  $models
     * @return string
     */
    protected function generateCsv($models)
    {
        if ($models->isEmpty()) {
            return ''; // Return empty string if no models
        }

        // Get the column titles from the first model's attributes
        $titles = implode(',', array_keys($models->first()->getAttributes()));

        // Map each model to a CSV row
        $csvRows = $models->map(function ($model) {
            return implode(',', collect($model->getAttributes())->map(function ($value) {
                // Handle null values
                if ($value === null) {
                    return '""';
                }

                // Cast to string, escape double quotes and wrap each value in quotes
                return '"'.str_replace('"', '""', (string) $value).'"';
            })->toArray());
        });

        // Prepend column titles to the CSV rows
        $csvRows->prepend($titles);

        // Return the CSV content as a string
        return $csvRows->implode(PHP_EOL);
    }

    /**
     * Streams the CSV content as a downloadable file.
     *
     * @param  string  $content
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function streamCsv($content)
    {
        $filename = $this->getCsvFilename(); // Make this dynamic

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function getCsvFilename(): string
    {
        return 'export_'.now()->format('Y-m-d_His').'.csv';
    }

    /**
     * Exports the given records as a CSV file.
     *
     * @param  \Illuminate\Support\Collection  $records
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function csv($records)
    {
        $csvContent = $this->generateCsv($records);

        return $this->streamCsv($csvContent);
    }
}
