<?php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportRenderer {
    public function render(array $report, string $format): string {
        return match ($format) {
            'pdf'=>$this->pdf($report), 'xlsx'=>$this->excel($report), 'csv'=>$this->csv($report),
            default=>throw new \InvalidArgumentException('Unsupported report format.'),
        };
    }
    private function pdf(array $report): string {
        $temp=storage_path('app/report-rendering');
        if (!is_dir($temp)) mkdir($temp,0700,true);
        $options=new Options(['defaultFont'=>'DejaVu Sans','isRemoteEnabled'=>false,'isPhpEnabled'=>false,'isJavascriptEnabled'=>false,'tempDir'=>$temp,'fontCache'=>$temp,'chroot'=>[resource_path('views/reports')]]);
        $pdf=new Dompdf($options);
        $pdf->setPaper('A4','landscape');
        $pdf->loadHtml(view('reports.service-evaluation',['report'=>$report])->render(),'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_text(735,565,'Page {PAGE_NUM} of {PAGE_COUNT}',$pdf->getFontMetrics()->getFont('DejaVu Sans'),8,[0.3,0.4,0.35]);
        return $pdf->output();
    }
    private function details(array $report): array {
        $rows=[['Control number','Subject','Status','Priority','Submitted','Completed','Category','Service type','Assigned personnel','Requesting office','Verified rating','Resolution hours']];
        foreach ($report['requests'] as $row) $rows[]=[
            $row['job_number'],$row['subject'],$row['status'],$row['priority'],$row['submitted_at'],$row['completed_at'],
            $row['category'],$row['service_type'],$row['personnel'],$row['requesting_office'],$row['rating'],
            $row['resolution_seconds']===null?null:round($row['resolution_seconds']/3600,2),
        ];
        return $rows;
    }
    private function csv(array $report): string {
        $stream=fopen('php://temp','r+');
        foreach ($this->details($report) as $row) {
            $safe=array_map(function ($value) {
                $text=(string)$value;
                return preg_match('/^(?:[\s\p{Z}\p{Cc}\p{Cf}\p{Mn}]*[=+@|\-]|[\t\r\n])/u',$text)?"'".$text:$text;
            },$row);
            fputcsv($stream,$safe,',','"','');
        }
        rewind($stream);$bytes=stream_get_contents($stream);fclose($stream);
        return $bytes;
    }
    private function excel(array $report): string {
        $book=new Spreadsheet();
        $book->getProperties()->setCreator('CLSU MISO Service Portal')->setTitle('Service evaluation report');
        $summary=$report['summary'];
        $sheets=['Summary'=>[
            ['Metric','Value'],['Requests received',$summary['total']],['Completed',$summary['completed']],['Open',$summary['open']],
            ['Completion rate (%)',$summary['completion_rate']],['Average verified rating',$summary['average_rating']],['Verified rating count',$summary['rating_count']],
            ['Average resolution (calendar hours)',$summary['average_resolution_hours']],['Eligible completed requests',$summary['resolution_count']],
            ['Invalid/missing completion times excluded',$summary['resolution_excluded']],['Report definition',$report['definition']],
            ['Rating definition',$report['rating_definition']],['Resolution definition',$report['resolution_definition']],
            ['Personnel attribution','One current active assignee per open request; final assignee for completed requests.'],['Recurring concerns method',$report['concern_definition']],
        ]];
        foreach (['categories'=>'Categories','priorities'=>'Priorities','statuses'=>'Statuses','personnel'=>'Personnel'] as $key=>$title) {
            $sheets[$title]=[['Group','Requests','Completed','Open','Average rating','Rating count','Average resolution hours','Eligible completed']];
            foreach ($report[$key] as $row) $sheets[$title][]=[$row['name'],$row['total'],$row['completed'],$row['open'],$row['average_rating'],$row['rating_count'],$row['average_resolution_hours'],$row['resolution_count']];
        }
        $sheets['Recurring concerns']=[['Repeated subject','Occurrences','Example job numbers']];
        foreach ($report['recurring_concerns'] as $row) $sheets['Recurring concerns'][]=[$row['subject'],$row['count'],implode(', ',$row['job_numbers'])];
        $sheets['Requests']=$this->details($report);
        $first=true;
        foreach ($sheets as $title=>$data) {
            $sheet=$first?$book->getActiveSheet():$book->createSheet();$first=false;
            $sheet->setTitle($title);
            $metadata=[['CLSU Service evaluation report — '.$title],[$report['office']],[$report['period_start'].' to '.$report['period_end']],['Generated '.$report['generated_at'].' · '.$report['timezone']]];
            foreach ([...$metadata,...$data] as $rowIndex=>$cells) foreach ($cells as $columnIndex=>$value) {
                if ($value===null) continue;
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($columnIndex+1).($rowIndex+1),$value,is_int($value)||is_float($value)?DataType::TYPE_NUMERIC:DataType::TYPE_STRING);
            }
            $lastColumn=Coordinate::stringFromColumnIndex(count($data[0]));$lastRow=4+count($data);
            $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('12553D');
            $sheet->getStyle('A5:'.$lastColumn.'5')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A5:'.$lastColumn.'5')->getFill()->setFillType('solid')->getStartColor()->setRGB('12553D');
            $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setVertical('top')->setWrapText(true);
            $sheet->getDefaultColumnDimension()->setWidth(23);
            $sheet->getColumnDimension('A')->setWidth($title==='Summary'?45:32);
            if ($title==='Summary') $sheet->getColumnDimension('B')->setWidth(105);
            if ($title==='Requests') $sheet->getColumnDimension('B')->setWidth(55);
            if ($title==='Recurring concerns') {$sheet->getColumnDimension('A')->setWidth(55);$sheet->getColumnDimension('C')->setWidth(90);}
            $sheet->freezePane('A6');
            if ($title!=='Summary') $sheet->setAutoFilter('A5:'.$lastColumn.$lastRow);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1,5);
        }
        $book->setActiveSheetIndex(0);$stream=fopen('php://temp','w+b');
        try {(new Xlsx($book))->save($stream);rewind($stream);return stream_get_contents($stream);}
        finally {fclose($stream);$book->disconnectWorksheets();}
    }
}
