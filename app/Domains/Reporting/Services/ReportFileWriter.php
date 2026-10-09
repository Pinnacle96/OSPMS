<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Reporting\Reports\BaseReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer;

class ReportFileWriter
{
    public const MIMES = ['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'];

    public function csvValue(mixed $value, string $type): string
    {
        $v = (string) ($value ?? '');
        if ($type === 'text' && preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $v)) {
            $v = "'".$v;
        }

        return $v;
    }

    public function write(BaseReport $r, User $u, array $criteria, string $format, string $path, array $summary): int
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));
        $absolute = $disk->path($path);
        $columns = $r->columns();
        $rows = $r->export($u, $criteria);
        $count = 0;
        if ($format === 'csv') {
            $h = fopen($absolute, 'wb');
            if (! $h) {
                throw new \RuntimeException('Export storage unavailable.');
            }
            try {
                fwrite($h, "\xEF\xBB\xBF");
                fputcsv($h, array_column($columns, 'label'), ',', '"', '');
                foreach ($rows as $row) {
                    if (fputcsv($h, array_map(fn ($c) => $this->csvValue($row[$c['key']] ?? null, $c['type']), $columns), ',', '"', '') === false) {
                        throw new \RuntimeException('Export write failed.');
                    }$count++;
                }
            } finally {
                fclose($h);
            }
        } elseif ($format === 'xlsx') {
            $o = new XlsxOptions;
            $o->setTempFolder(storage_path('framework/cache'));
            $o->SHOULD_USE_INLINE_STRINGS = false;
            $w = new Writer($o);
            $w->openToFile($absolute);
            $line = fn ($values, $style = null) => new Row(array_map(fn ($v) => new StringCell((string) ($v ?? ''), null), $values), $style);
            $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('174A3B')->setShouldWrapText();
            try {
                $w->getCurrentSheet()->setName('Records');
                $w->addRow($line(array_column($columns, 'label'), $header));
                foreach ($rows as $row) {
                    $w->addRow($line(array_map(fn ($c) => $row[$c['key']] ?? '', $columns)));
                    $count++;
                }
                $w->addNewSheetAndMakeItCurrent()->setName('Summary');
                foreach ([['Report', $r->title], ['Basis', $r->basis], ['From', $criteria['from']], ['To', $criteria['to']], ['Timezone', config('ospm.timezone')], ['Currency', 'NGN'], ['Generated', now()->toIso8601String()], ...array_map(fn ($s) => [$s['label'], $s['value']], $summary), ...array_map(fn ($key) => [$key, $criteria[$key]], array_keys(array_diff_key($criteria, ['from' => 1, 'to' => 1])))] as $v) {
                    $w->addRow($line($v));
                }
            } finally {
                $w->close();
            }
        } else {
            $data = [];
            foreach ($rows as $row) {
                $data[] = $row;
                if (++$count > config('reports.pdf_max_rows')) {
                    throw ValidationException::withMessages(['format' => 'PDF output exceeds the row limit. Narrow the filters or choose CSV or Excel.']);
                }
            }
            $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => resource_path('views'), 'tempDir' => storage_path('framework/cache'), 'fontCache' => storage_path('framework/cache')]));
            $pdf->loadHtml(view('reports.pdf', ['report' => $r, 'criteria' => $criteria, 'columns' => $columns, 'rows' => $data, 'summary' => $summary])->render(), 'UTF-8');
            $pdf->setPaper('A4', 'landscape');
            $pdf->render();
            $pdf->getCanvas()->page_text(730, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8);
            if (! $disk->put($path, $pdf->output())) {
                throw new \RuntimeException('Export storage unavailable.');
            }
        }

        return $count;
    }
}
