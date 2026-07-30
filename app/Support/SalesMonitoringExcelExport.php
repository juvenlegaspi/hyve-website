<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesMonitoringExcelExport
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function download(array $data): StreamedResponse
    {
        $filters = $data['filters'];
        $fileName = 'hyve-sales-monitoring-'.$filters['date_from'].'-to-'.$filters['date_to'].'.xls';

        return response()->streamDownload(
            fn () => print $this->workbook($data),
            $fileName,
            [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function workbook(array $data): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:o="urn:schemas-microsoft-com:office:office" '
            .'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:html="http://www.w3.org/TR/REC-html40">'
            .'<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">'
            .'<Author>HYVE</Author><Company>HYVE Coworking Space</Company>'
            .'<Title>Sales Monitoring Executive Report</Title>'
            .'</DocumentProperties>'
            .'<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">'
            .'<ProtectStructure>False</ProtectStructure><ProtectWindows>False</ProtectWindows>'
            .'</ExcelWorkbook>'
            .$this->styles()
            .$this->executiveSummarySheet($data)
            .$this->trendSheet($data['trend'])
            .$this->breakdownSheet($data)
            .$this->spacePerformanceSheet($data)
            .$this->demandAndBookingsSheet($data)
            .$this->transactionsSheet($data['exportTransactions'])
            .'</Workbook>';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function executiveSummarySheet(array $data): string
    {
        $filters = $data['filters'];
        $summary = $data['summary'];
        $source = match ($filters['source']) {
            'web' => 'Online bookings',
            'admin' => 'Walk-in bookings',
            default => 'All booking sources',
        };
        $method = $filters['method'] === 'all'
            ? 'All payment channels'
            : ucfirst(str_replace('_', ' ', (string) $filters['method']));

        $rows = [
            $this->titleRow('HYVE SALES MONITORING', 5),
            $this->subtitleRow('Executive revenue report', 5),
            $this->blankRow(),
            $this->sectionRow('REPORT PARAMETERS', 5),
            $this->row([
                $this->cell('Reporting period', 'label'),
                $this->cell($filters['date_from'].' to '.$filters['date_to'], 'text', 2),
                $this->cell('Generated', 'label'),
                $this->cell(now()->format('M j, Y g:i A'), 'text'),
            ]),
            $this->row([
                $this->cell('Booking source', 'label'),
                $this->cell($source, 'text', 2),
                $this->cell('Payment channel', 'label'),
                $this->cell($method, 'text'),
            ]),
            $this->blankRow(),
            $this->sectionRow('EXECUTIVE SUMMARY', 5),
            $this->row([
                $this->cell('Verified collections', 'kpiLabel'),
                $this->numberCell($summary['gross_sales'], 'kpiCurrency'),
                $this->cell('Verified transactions', 'kpiLabel'),
                $this->numberCell($summary['transactions'], 'kpiNumber'),
                $this->cell('Average transaction', 'kpiLabel'),
                $this->numberCell($summary['average_transaction'], 'kpiCurrency'),
            ], 34),
            $this->row([
                $this->cell('Pending verification', 'kpiLabel'),
                $this->numberCell($summary['pending_amount'], 'kpiCurrencyGold'),
                $this->cell('Outstanding balance', 'kpiLabel'),
                $this->numberCell($summary['outstanding_balance'], 'kpiCurrencyGold'),
                $this->cell('New booked value', 'kpiLabel'),
                $this->numberCell($summary['booked_value'], 'kpiCurrency'),
            ], 34),
            $this->blankRow(),
            $this->sectionRow('COMMERCIAL SNAPSHOT', 5),
            $this->row([
                $this->cell('Metric', 'header'),
                $this->cell('Value', 'header'),
                $this->cell('Context', 'header', 3),
            ]),
            $this->row([
                $this->cell('Verified collections', 'bodyStrong'),
                $this->numberCell($summary['gross_sales'], 'currency'),
                $this->cell('Approved payments verified within the reporting period.', 'body', 3),
            ]),
            $this->row([
                $this->cell('Pending payments', 'bodyStrong'),
                $this->numberCell($summary['pending_amount'], 'currency'),
                $this->cell(number_format($summary['pending_count']).' payment(s) awaiting verification.', 'body', 3),
            ]),
            $this->row([
                $this->cell('Outstanding balance', 'bodyStrong'),
                $this->numberCell($summary['outstanding_balance'], 'currency'),
                $this->cell('Remaining balance from confirmed bookings created in the period.', 'body', 3),
            ]),
            $this->row([
                $this->cell('Discounts granted', 'bodyStrong'),
                $this->numberCell($summary['discounts'], 'currency'),
                $this->cell('Recorded booking discounts within the reporting period.', 'body', 3),
            ]),
            $this->blankRow(),
            $this->sectionRow('TODAY\'S LIVE SALES', 5),
            $this->row([
                $this->cell('Verified collections', 'bodyStrong'),
                $this->numberCell($data['todayLiveSales']['sales'], 'currency'),
                $this->cell('Transactions', 'bodyStrong'),
                $this->numberCell($data['todayLiveSales']['transactions'], 'number'),
                $this->cell('Average transaction', 'bodyStrong'),
                $this->numberCell($data['todayLiveSales']['average'], 'currency'),
            ]),
            $this->blankRow(),
            $this->sectionRow('BALANCE COLLECTION MONITORING', 5),
            $this->row([
                $this->cell('Category', 'header'),
                $this->cell('Bookings', 'header'),
                $this->cell('Amount', 'header'),
                $this->cell('Category', 'header'),
                $this->cell('Bookings', 'header'),
                $this->cell('Amount', 'header'),
            ]),
        ];
        $agingRows = $data['outstandingAging']->values();
        $forecastRows = $data['upcomingCollections']->values();
        $monitoringRows = max($agingRows->count(), $forecastRows->count());

        for ($index = 0; $index < $monitoringRows; $index++) {
            $aging = $agingRows->get($index);
            $forecast = $forecastRows->get($index);
            $rows[] = $this->row([
                $this->cell((string) ($aging['label'] ?? ''), 'bodyStrong'),
                $this->numberCell($aging['count'] ?? 0, 'number'),
                $this->numberCell($aging['amount'] ?? 0, 'currency'),
                $this->cell((string) ($forecast['label'] ?? ''), 'bodyStrong'),
                $this->numberCell($forecast['count'] ?? 0, 'number'),
                $this->numberCell($forecast['amount'] ?? 0, 'currency'),
            ]);
        }

        $rows = [
            ...$rows,
            $this->blankRow(),
            $this->row([
                $this->cell('Prepared from verified HYVE payment records. Pending and rejected payments are excluded from collected sales.', 'footnote', 5),
            ]),
        ];

        return $this->worksheet(
            'Executive Summary',
            [145, 115, 145, 115, 145, 135],
            implode('', $rows),
            4,
        );
    }

    private function trendSheet(Collection $trend): string
    {
        $rows = [
            $this->titleRow('COLLECTION PERFORMANCE', 3),
            $this->subtitleRow('Verified sales grouped by verification date', 3),
            $this->blankRow(),
            $this->row([
                $this->cell('Period', 'header'),
                $this->cell('Verified transactions', 'header'),
                $this->cell('Collected sales', 'header'),
                $this->cell('Share of total', 'header'),
            ]),
        ];
        $total = max(0.01, (float) $trend->sum('amount'));

        foreach ($trend as $point) {
            $rows[] = $this->row([
                $this->cell((string) $point['label'], 'bodyStrong'),
                $this->numberCell($point['transactions'], 'number'),
                $this->numberCell($point['amount'], 'currency'),
                $this->numberCell(((float) $point['amount']) / $total, 'percent'),
            ]);
        }

        $rows[] = $this->row([
            $this->cell('TOTAL', 'totalLabel'),
            $this->numberCell($trend->sum('transactions'), 'totalNumber'),
            $this->numberCell($trend->sum('amount'), 'totalCurrency'),
            $this->numberCell($trend->sum('amount') > 0 ? 1 : 0, 'totalPercent'),
        ]);

        return $this->worksheet('Sales Trend', [130, 125, 130, 110], implode('', $rows), 4);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function breakdownSheet(array $data): string
    {
        $rows = [
            $this->titleRow('REVENUE BREAKDOWN', 3),
            $this->subtitleRow('Sales composition by channel, source, and space', 3),
            $this->blankRow(),
        ];

        foreach ([
            ['title' => 'PAYMENT CHANNEL MIX', 'items' => $data['methodBreakdown']],
            ['title' => 'BOOKING SOURCE MIX', 'items' => $data['sourceBreakdown']],
            ['title' => 'TOP REVENUE SPACES', 'items' => $data['roomBreakdown']],
        ] as $section) {
            $items = $section['items'];
            $total = max(0.01, (float) $items->sum('amount'));
            $rows[] = $this->sectionRow($section['title'], 3);
            $rows[] = $this->row([
                $this->cell('Category', 'header'),
                $this->cell('Transactions', 'header'),
                $this->cell('Collected sales', 'header'),
                $this->cell('Share', 'header'),
            ]);

            if ($items->isEmpty()) {
                $rows[] = $this->row([$this->cell('No verified sales for this period.', 'empty', 3)]);
            } else {
                foreach ($items as $item) {
                    $rows[] = $this->row([
                        $this->cell((string) $item['label'], 'bodyStrong'),
                        $this->numberCell($item['transactions'], 'number'),
                        $this->numberCell($item['amount'], 'currency'),
                        $this->numberCell(((float) $item['amount']) / $total, 'percent'),
                    ]);
                }
            }

            $rows[] = $this->blankRow();
        }

        return $this->worksheet('Revenue Breakdown', [175, 105, 130, 95], implode('', $rows), 4);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function spacePerformanceSheet(array $data): string
    {
        $comparison = $data['spaceMonthlyComparison'];
        $weekly = $data['spaceWeeklySales'];
        $spaceLabels = $comparison['rows']->pluck('label')->all();
        $rows = [
            $this->titleRow('SPACE SALES PERFORMANCE', 5),
            $this->subtitleRow('Verified collections allocated across HYVE space categories', 5),
            $this->blankRow(),
            $this->sectionRow('MONTHLY SALES COMPARISON', 5),
            $this->row([
                $this->cell('Space', 'header'),
                $this->cell($comparison['current_label'], 'header'),
                $this->cell($comparison['previous_label'], 'header'),
                $this->cell('Current window', 'header'),
                $this->cell('Previous window', 'header', 1),
            ]),
        ];

        foreach ($comparison['rows'] as $item) {
            $rows[] = $this->row([
                $this->cell((string) $item['label'], 'bodyStrong'),
                $this->numberCell($item['current'], 'currency'),
                $this->numberCell($item['previous'], 'currency'),
                $this->cell((string) $comparison['current_period'], 'body'),
                $this->cell((string) $comparison['previous_period'], 'body', 1),
            ]);
        }

        $rows[] = $this->row([
            $this->cell('TOTAL', 'totalLabel'),
            $this->numberCell($comparison['rows']->sum('current'), 'totalCurrency'),
            $this->numberCell($comparison['rows']->sum('previous'), 'totalCurrency'),
            $this->cell('Comparable elapsed-day periods', 'footnote', 2),
        ]);
        $rows[] = $this->blankRow();
        $rows[] = $this->sectionRow('WEEKLY SALES BY SPACE', 5);
        $headerCells = [$this->cell('Week (Monday - Sunday)', 'header')];

        foreach ($spaceLabels as $label) {
            $headerCells[] = $this->cell((string) $label, 'header');
        }

        $headerCells[] = $this->cell('Total', 'header');
        $rows[] = $this->row($headerCells);

        foreach ($weekly as $week) {
            $cells = [$this->cell((string) $week['label'], 'bodyStrong')];

            foreach ($spaceLabels as $label) {
                $cells[] = $this->numberCell($week['spaces'][$label] ?? 0, 'currency');
            }

            $cells[] = $this->numberCell($week['total'], 'totalCurrency');
            $rows[] = $this->row($cells);
        }

        $rows[] = $this->blankRow();
        $rows[] = $this->sectionRow('SPACE UTILIZATION & REVENUE EFFICIENCY', 5);
        $rows[] = $this->row([
            $this->cell('Space', 'header'),
            $this->cell('Booked hours', 'header'),
            $this->cell('Available hours', 'header'),
            $this->cell('Utilization', 'header'),
            $this->cell('Verified revenue', 'header'),
            $this->cell('Revenue / hour', 'header'),
        ]);

        foreach ($data['roomUtilization'] as $utilization) {
            $rows[] = $this->row([
                $this->cell((string) $utilization['label'], 'bodyStrong'),
                $this->numberCell($utilization['booked_hours'], 'number'),
                $this->numberCell($utilization['available_hours'], 'number'),
                $this->numberCell(((float) $utilization['utilization']) / 100, 'percent'),
                $this->numberCell($utilization['revenue'], 'currency'),
                $this->numberCell($utilization['revenue_per_hour'], 'currency'),
            ]);
        }

        return $this->worksheet(
            'Space Performance',
            [145, 125, 125, 125, 125, 125],
            implode('', $rows),
            4,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function demandAndBookingsSheet(array $data): string
    {
        $demand = $data['demandHeatmap'];
        $sourceCounts = $data['bookingSourceCounts'];
        $lifecycle = $data['bookingLifecycle'];
        $rows = [
            $this->titleRow('CUSTOMER DEMAND & BOOKING ANALYSIS', 7),
            $this->subtitleRow('Booking volume, guest demand, source, and lifecycle indicators', 7),
            $this->blankRow(),
            $this->sectionRow('DEMAND HEATMAP - BOOKINGS / GUESTS', 7),
        ];
        $headers = [$this->cell('Time block', 'header')];

        foreach ($demand['days'] as $day) {
            $headers[] = $this->cell((string) $day, 'header');
        }

        $rows[] = $this->row($headers);

        foreach ($demand['rows'] as $demandRow) {
            $cells = [$this->cell((string) $demandRow['label'], 'bodyStrong')];

            foreach ($demand['days'] as $day) {
                $cell = $demandRow['cells'][$day];
                $cells[] = $this->cell(
                    number_format($cell['bookings']).' booking(s) / '.number_format($cell['guests']).' guest(s)',
                    'body',
                );
            }

            $rows[] = $this->row($cells, 28);
        }

        $rows[] = $this->row([
            $this->cell('TOTAL DEMAND', 'totalLabel'),
            $this->cell(
                number_format($demand['booking_total']).' booking(s) / '.number_format($demand['guest_total']).' guest(s)',
                'totalLabel',
                6,
            ),
        ]);
        $rows[] = $this->blankRow();
        $rows[] = $this->sectionRow('BOOKING SOURCE', 7);
        $rows[] = $this->row([
            $this->cell('Source', 'header'),
            $this->cell('Bookings', 'header'),
            $this->cell('Share', 'header', 5),
        ]);

        foreach ($sourceCounts as $source) {
            $rows[] = $this->row([
                $this->cell((string) $source['label'], 'bodyStrong'),
                $this->numberCell($source['count'], 'number'),
                $this->numberCell(((float) $source['percentage']) / 100, 'percent'),
            ]);
        }

        $rows[] = $this->blankRow();
        $rows[] = $this->sectionRow('BOOKING LIFECYCLE', 7);
        $rows[] = $this->row([
            $this->cell('Indicator', 'header'),
            $this->cell('Bookings', 'header', 6),
        ]);

        foreach ($lifecycle['rows'] as $item) {
            $rows[] = $this->row([
                $this->cell((string) $item['label'], 'bodyStrong'),
                $this->numberCell($item['count'], 'number'),
            ]);
        }

        $rows[] = $this->row([
            $this->cell('TOTAL BOOKINGS', 'totalLabel'),
            $this->numberCell($lifecycle['total'], 'totalNumber'),
        ]);
        $rows[] = $this->row([
            $this->cell(
                'Rescheduled is an activity indicator and can overlap with the current confirmed, pending, or cancelled status.',
                'footnote',
                7,
            ),
        ]);

        return $this->worksheet(
            'Demand & Bookings',
            [145, 115, 115, 115, 115, 115, 115, 115],
            implode('', $rows),
            4,
        );
    }

    private function transactionsSheet(Collection $transactions): string
    {
        $rows = [
            $this->titleRow('VERIFIED TRANSACTION DETAILS', 9),
            $this->subtitleRow('Complete approved payment register for the selected reporting period', 9),
            $this->blankRow(),
            $this->row([
                $this->cell('Payment ID', 'header'),
                $this->cell('Booking ID', 'header'),
                $this->cell('Reference No.', 'header'),
                $this->cell('Customer', 'header'),
                $this->cell('Source', 'header'),
                $this->cell('Payment type', 'header'),
                $this->cell('Method', 'header'),
                $this->cell('Verified by', 'header'),
                $this->cell('Verified date', 'header'),
                $this->cell('Amount', 'header'),
            ]),
        ];

        foreach ($transactions as $transaction) {
            $rows[] = $this->row([
                $this->numberCell($transaction['id'], 'number'),
                $this->numberCell($transaction['booking_id'], 'number'),
                $this->cell((string) $transaction['reference'], 'body'),
                $this->cell((string) $transaction['customer'], 'bodyStrong'),
                $this->cell((string) $transaction['source'], 'body'),
                $this->cell((string) $transaction['type'], 'body'),
                $this->cell((string) $transaction['method'], 'body'),
                $this->cell((string) $transaction['verified_by'], 'body'),
                $this->cell((string) $transaction['verified_at'], 'body'),
                $this->numberCell($transaction['amount'], 'currency'),
            ]);
        }

        if ($transactions->isEmpty()) {
            $rows[] = $this->row([$this->cell('No verified transactions for this reporting period.', 'empty', 9)]);
        } else {
            $rows[] = $this->row([
                $this->cell('TOTAL', 'totalLabel', 8),
                $this->numberCell($transactions->sum('amount'), 'totalCurrency'),
            ]);
        }

        return $this->worksheet(
            'Transaction Details',
            [75, 75, 145, 170, 90, 95, 100, 140, 145, 110],
            implode('', $rows),
            4,
        );
    }

    /**
     * @param  array<int, int>  $widths
     */
    private function worksheet(string $name, array $widths, string $rows, int $freezeRow): string
    {
        $columns = collect($widths)
            ->map(fn (int $width): string => '<Column ss:AutoFitWidth="0" ss:Width="'.$width.'"/>')
            ->implode('');

        return '<Worksheet ss:Name="'.$this->escape($name).'"><Table>'
            .$columns.$rows
            .'</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
            .'<FreezePanes/><FrozenNoSplit/><SplitHorizontal>'.$freezeRow.'</SplitHorizontal>'
            .'<TopRowBottomPane>'.$freezeRow.'</TopRowBottomPane><ActivePane>2</ActivePane>'
            .'<Selected/><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios>'
            .'<PageSetup><Layout x:Orientation="Landscape"/><Header x:Margin="0.3"/>'
            .'<Footer x:Margin="0.3"/></PageSetup>'
            .'</WorksheetOptions></Worksheet>';
    }

    private function styles(): string
    {
        $border = '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E4E9E1"/>'
            .'<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E4E9E1"/>'
            .'<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E4E9E1"/>'
            .'<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E4E9E1"/></Borders>';

        return '<Styles>'
            .'<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/>'
            .'<Font ss:FontName="Calibri" ss:Size="10" ss:Color="#263B31"/></Style>'
            .'<Style ss:ID="title"><Font ss:FontName="Calibri" ss:Size="18" ss:Bold="1" ss:Color="#FFFFFF"/>'
            .'<Interior ss:Color="#173B29" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>'
            .'<Style ss:ID="subtitle"><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#DDE9DE"/>'
            .'<Interior ss:Color="#315E3D" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>'
            .'<Style ss:ID="section"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#8A682B"/>'
            .'<Interior ss:Color="#F7EFDf" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/></Style>'
            .'<Style ss:ID="header"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>'
            .'<Interior ss:Color="#467348" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
            .'<Style ss:ID="label"><Font ss:Bold="1" ss:Color="#536258"/><Interior ss:Color="#F2F5EF" ss:Pattern="Solid"/>'.$border.'</Style>'
            .'<Style ss:ID="text"><Alignment ss:Vertical="Center"/>'.$border.'</Style>'
            .'<Style ss:ID="body"><Alignment ss:Vertical="Center" ss:WrapText="1"/>'.$border.'</Style>'
            .'<Style ss:ID="bodyStrong"><Font ss:Bold="1" ss:Color="#173B29"/><Alignment ss:Vertical="Center" ss:WrapText="1"/>'.$border.'</Style>'
            .'<Style ss:ID="number"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><NumberFormat ss:Format="#,##0"/>'.$border.'</Style>'
            .'<Style ss:ID="currency"><Font ss:Bold="1" ss:Color="#315E3D"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/>'.$border.'</Style>'
            .'<Style ss:ID="percent"><Alignment ss:Horizontal="Center"/><NumberFormat ss:Format="0.0%"/>'.$border.'</Style>'
            .'<Style ss:ID="kpiLabel"><Font ss:Size="10" ss:Bold="1" ss:Color="#536258"/><Interior ss:Color="#EDF4E8" ss:Pattern="Solid"/>'.$border.'</Style>'
            .'<Style ss:ID="kpiCurrency"><Font ss:Size="13" ss:Bold="1" ss:Color="#173B29"/><Interior ss:Color="#EDF4E8" ss:Pattern="Solid"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/>'.$border.'</Style>'
            .'<Style ss:ID="kpiCurrencyGold"><Font ss:Size="13" ss:Bold="1" ss:Color="#795D29"/><Interior ss:Color="#FBF4E6" ss:Pattern="Solid"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/>'.$border.'</Style>'
            .'<Style ss:ID="kpiNumber"><Font ss:Size="13" ss:Bold="1" ss:Color="#173B29"/><Interior ss:Color="#EDF4E8" ss:Pattern="Solid"/><NumberFormat ss:Format="#,##0"/>'.$border.'</Style>'
            .'<Style ss:ID="totalLabel"><Font ss:Bold="1" ss:Color="#173B29"/><Interior ss:Color="#E3EDDD" ss:Pattern="Solid"/>'.$border.'</Style>'
            .'<Style ss:ID="totalNumber"><Font ss:Bold="1" ss:Color="#173B29"/><Interior ss:Color="#E3EDDD" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/><NumberFormat ss:Format="#,##0"/>'.$border.'</Style>'
            .'<Style ss:ID="totalCurrency"><Font ss:Bold="1" ss:Color="#173B29"/><Interior ss:Color="#E3EDDD" ss:Pattern="Solid"/><NumberFormat ss:Format="&quot;Php &quot;#,##0.00"/>'.$border.'</Style>'
            .'<Style ss:ID="totalPercent"><Font ss:Bold="1" ss:Color="#173B29"/><Interior ss:Color="#E3EDDD" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center"/><NumberFormat ss:Format="0.0%"/>'.$border.'</Style>'
            .'<Style ss:ID="empty"><Font ss:Italic="1" ss:Color="#8D958E"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'.$border.'</Style>'
            .'<Style ss:ID="footnote"><Font ss:Size="9" ss:Italic="1" ss:Color="#747E76"/><Alignment ss:WrapText="1"/></Style>'
            .'</Styles>';
    }

    private function titleRow(string $title, int $mergeAcross): string
    {
        return $this->row([$this->cell($title, 'title', $mergeAcross)], 32);
    }

    private function subtitleRow(string $subtitle, int $mergeAcross): string
    {
        return $this->row([$this->cell($subtitle, 'subtitle', $mergeAcross)], 22);
    }

    private function sectionRow(string $title, int $mergeAcross): string
    {
        return $this->row([$this->cell($title, 'section', $mergeAcross)], 22);
    }

    private function blankRow(): string
    {
        return '<Row ss:Height="8"/>';
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function row(array $cells, ?int $height = null): string
    {
        return '<Row'.($height ? ' ss:AutoFitHeight="0" ss:Height="'.$height.'"' : '').'>'
            .implode('', $cells)
            .'</Row>';
    }

    private function cell(string $value, string $style, int $mergeAcross = 0): string
    {
        return '<Cell ss:StyleID="'.$style.'"'.($mergeAcross > 0 ? ' ss:MergeAcross="'.$mergeAcross.'"' : '').'>'
            .'<Data ss:Type="String">'.$this->escape($value).'</Data></Cell>';
    }

    private function numberCell(float|int|string|null $value, string $style): string
    {
        return '<Cell ss:StyleID="'.$style.'"><Data ss:Type="Number">'.(float) ($value ?? 0).'</Data></Cell>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
