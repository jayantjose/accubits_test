<?php

namespace App\Jobs;

use Exception;
use App\Mail\SendMail;
use App\Models\CsvData;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $messageData;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($messageData)
    {
        $this->messageData = $messageData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {

            
            $message  ="<p>" . "File Name: " . $this->messageData['filename'] ."</p>";
            $message .="<p>" . "Messages: " . json_encode($this->messageData['message']) ."</p>";

            $data = $this->fetchCsv($this->messageData['csvfile']); 

            $i=0;
            foreach($data as $rdata) {
                if($i>0) {
                    $csvData = new CsvData;
                    $csvData->module_code = $rdata[0];
                    $csvData->module_name = $rdata[1];
                    $csvData->module_term = $rdata[2];
                    $csvData->save();
                }
                $i++;
            }
            
            $data_imported = $i-1;
            $message .="<p>" . "Import Status: " . "Data Imported: " . $data_imported ."</p>";

            $details =[
                'from' => 'mail@jayantjose.com',
                'from_name' => 'Jayant Jose',
                'subject' => 'Process Status',
                'template' => 'Emails.template1',
                'title' => 'CSV File Processing Status',
                'body' =>  $message
            ];
        
            Mail::to('charush@accubits.com')->cc("mail@jayantjose.com")->send(new SendMail($details));
        }
        catch (Exception $ex) {

        }
    }


    public function fetchCsv($filename)
    {
        $data = array();
        if (($handle = fopen($filename, 'r')) !== false)
        {
            while (($row = fgetcsv($handle, 1000, ",")) !== false)
            {
                $data[] = $row;
            }
            fclose($handle);
        }

        return $data;
    }

}
