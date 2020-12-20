<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class CsvUploader extends Controller
{
    private $configurations = [
                                "headers"=>["Module_code","Module_name","Module_term"],
                                "validations" =>[
                                    "*.Module_code" => 'required|min:2|max:20',
                                    "*.Module_name" => 'required|min:2|max:50',
                                    "*.Module_term" => 'required|min:2|max:50'
                                ]
                             ];

    public function uploadCsv(Request $request) {
        $validator = Validator::make($request->all(), [
            'csvfile' => 'required|mimes:csv,txt|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(),400);
        }

        // Declare Error Message Array to show all error messages as one result
        $error_messages = [];
        if(request()->csvfile) {
            // Validate Extension since validator will not trap if the uploaded file is a txt file
            $extension = $request->csvfile->getClientOriginalExtension();
            if(strtolower($extension) != "csv")  {
                $error_messages[] = "Only file with CSV extension will be accepted";
            }
        
            // Import CSV to array for row wise validations
            $data = $this->fetchCsv($request); // Fetch the CSV data

            if(count($data)>0) {
                // Validate Input Data Column Count
                if(!$this->validateColumnCount($data)) {
                    $reqcount = count($this->configurations["headers"])-1;
                    $error_messages[] = "Column count in CSV data is not matching with required column count: " . $reqcount;
                }

                // Validate Headings
                if(!$this->validateHeaderRow($data)) {
                    $error_messages[] = "CSV Heading Column names are mismatching. Acceptable headers are: ".implode(',',$this->configurations["headers"]);
                }

                // Validate row data for finding missing and non allowable characters
                $validator = Validator::make($data, $this->configurations["validations"]);
                
                //Now check validation:
                if ($validator->fails()) 
                { 
                    $error_messages[]["Row Validations"] = $validator->errors();
                }

                // Check if there is any errors found. If no errors then copy the file to import folder.
                // From this folder csv convertion program will read file and save to database
                if(count($error_messages)<=0) {
                    $fileName = request()->csvfile->getClientOriginalName();
                    if($request->csvfile->storeAs('/',$fileName,'csvfiles-in')) {
                        return response()->json([
                            "success" => true,
                            "message" => "File successfully uploaded and send to the process que",
                            "file" => $fileName
                        ]);
                    }
                }
                else {
                    return response()->json($error_messages,400);
                }
            }
            else {
                $error_messages[] = "No Data Found";
                return response()->json($error_messages,400);
            }
        }

    }

    // Return a schedule CSV
    public function fetchCsv($request)
    {
        $filename = request()->csvfile->getRealPath();

        $header = null;
        $data = array();
        if (($handle = fopen($filename, 'r')) !== false)
        {
            while (($row = fgetcsv($handle, 1000, ",")) !== false)
            {
                if (!$header)
                    $header = $row;
                else
                    $data[] = array_combine($header, $row);
            }
            fclose($handle);
        }

        return $data;
    }
    
    public function validateHeaderRow($data)
    {
        $validate = false;
        if(array_keys($data[0]) == $this->configurations["headers"]) {
            $validate = true;
        }
        return $validate;

    }

    public function validateColumnCount($data)
    {
        $validate = false;
        
        if(count($data[0]) == count($this->configurations["headers"])) {
            $validate = true;
        }
        return $validate;
    }

    
}
