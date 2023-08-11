<?php

class OleeoFeedParser {

    /** 
    * Converts XML File to JSON FIle
    * @param string $sourceFile Source XML File to be parsed
    * @param string $outputFile JSON File that will be created
    * @param array $optionalFields 
    * @param string $feedType
    * @return array $parseResult 
    **/
    public function parseToJSON(string $sourceFile, string $outputFile, array $optionalFields = [], $feedType = 'complex'){

        $parseResult = ['success' => false, 'errors' => [] ];

        if(!file_exists($sourceFile)){
            $parseResult['errors'][] = "Error Parsing [sourceFile: $sourceFile] - source file does not exist";
            return $parseResult;
        }
            
        $xmlContent = simplexml_load_file($sourceFile);

        if (!$xmlContent) {
            $parseResult['errors'][] = "Error Parsing [sourceFile: $sourceFile] - source file could not be loaded. Please check this file is valid XML";
            return $parseResult;
        }

        $jobs = [];
        $totalJobs = count($xmlContent->entry);

        if ($totalJobs > 0){

            for ($x = 0; $x < $totalJobs; $x++) {
                
                $newJob = $this->validateJobDetails($xmlContent->entry[$x], $optionalFields, $feedType);

                if($newJob != false){

                    $job_hash = md5(json_encode($newJob));
                    $newJob['hash'] = $job_hash;
                    $jobs[] = $newJob;
                }
                            
            }
        }

        $outputArray = [
            'objectType' => "job",
            'objects' => $jobs
        ];
        
        $outputJSON = json_encode($outputArray);

        if(!$outputJSON){
            $parseResult['errors'][] = "Error Parsing Feed [sourceFile: $sourceFile] - Error encoding feed data";
        }
        
        $writeFileResult = file_put_contents($outputFile, $outputJSON);

        if($writeFileResult === false){
            $parseResult['errors'][] = "Error Parsing Feed [sourceFile: $sourceFile] - Output file failed to write [outputFile: $outputFile]";
        }
        else {
            $parseResult['success'] = true;
        }

        return $parseResult;
        
    }

    function validateJobDetails($jobXML, array $optionalFields = [], $feedType = 'complex'){
        $job = [];
        $jobTitle = (string) $jobXML->title; 
        $jobURL = (string) $jobXML->id; 
        $jobContent = $jobXML->content;

        $job['title'] = $jobTitle; //Question - do we want to strip the ID?
        $job['url'] = $jobURL;
        
        // Job title and Job URL are required
        if (empty($jobTitle) || empty($jobURL)){
            return false;
        }

        if ($feedType == 'simple'){
            $job = $this->validateOptionalFieldsbyNewLine($job, $jobContent, $optionalFields);
        }
        else {
            $job = $this->validateOptionalFieldsbySpan($job, $jobContent, $optionalFields);
        }

        //Strip Vacancy ID from title
        if(array_key_exists('id', $job) && !empty($job['id'])){
            $job['title'] = trim(str_replace($job['id'] . ' -', '' , $job['title']));
        }

        return $job;
    }
   
    function validateOptionalFieldsbySpan($job, $jobContent, array $optionalFields = []){
        //Question - what happens if fields not there - in feed but blank or not there.

        if(in_array('salaryRange', $optionalFields)){
            $job['salaryRange'] = [];
        }

        if(in_array('addresses', $optionalFields)){
            $job['addresses'] = [];
        }

        if(in_array('cities', $optionalFields)){
            $job['cities'] = [];
        }

        if(in_array('regions', $optionalFields)){
            $job['regions'] = [];
        }
    
        if(in_array('roleTypes', $optionalFields)){
            $job['roleTypes'] = [];
        }

        if(in_array('contractTypes', $optionalFields)){
            $job['contractTypes'] = [];
        }

        $totalSpans = count($jobContent->div->span);

        //loops through span elements to find job details by matching to itemprop value
        for ($y = 0; $y < $totalSpans; $y++) {

            $currentSpan = $jobContent->div->span[$y];
 
            if(in_array('id', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Vacancy ID") {
                    $job['id'] = (string) $currentSpan;
                }
            }

            if(in_array('closingDate', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Closing Date") {
                    //convert date to timestamp
                    $job['closingDate'] = preg_replace("/[^0-9]/", "", strtotime((string) $currentSpan));
                }
            }

            if(in_array('salary', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Salary Minimum") {
        
                    $salary_validated = $this->validateSalary((string) $currentSpan);
        
                    if (array_key_exists('min', $salary_validated)) {
                        $job['salaryMin'] = $salary_validated['min'];
                    }
        
                    if (array_key_exists('max', $salary_validated)) {
                        $job['salaryMax'] = $salary_validated['max'];
                    }

                    if (array_key_exists('london', $salary_validated)) {
                        $job['salaryLondon'] = $salary_validated['london'];
                    }
                }
            }

            // Salary Range
            if(in_array('salaryRange', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Salary Range") { 
                    array_push($job['salaryRange'], (string) $currentSpan);
                }
            }

            // Building/Site - renamed to Addresses
            if(in_array('addresses', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Building/Site") { //Question - some in caps some not?
                    array_push($job['addresses'], (string) $currentSpan);
                }
            }

            //  Cities
            if(in_array('cities', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "City/Town") {
                    array_push($job['cities'], trim((string) $currentSpan));
                }
            }
                            
            //  Geographical Region(s)
            if(in_array('regions', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Geographical Region(s)") {
                    array_push($job['regions'], (string) $currentSpan);   
                }
            }

            //  Role Type
            if(in_array('roleTypes', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Role Type") {
                    array_push($job['roleTypes'], (string) $currentSpan);
                }
            }

            //  Working Pattern - renamed to Contract Type
            if(in_array('contractTypes', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Working Pattern") {
                    array_push($job['contractTypes'], (string) $currentSpan);
                }
            }

            if(in_array('desc', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Job description Additional Information") {
                    $job['desc'] = (string) $currentSpan->asXML();
                }
            }

            if(in_array('additionalInfo', $optionalFields)){
                if ($currentSpan->attributes()->itemprop[0] == "Additional Information") {
                    $job['additionalInfo'] = (string) $currentSpan->asXML();
                }
            }


        }

        return $job;
    }

    function validateOptionalFieldsbyNewLine($job, $jobContent, array $optionalFields = []){
    
        if(in_array('cities', $optionalFields)){
            $job['cities'] = [];
        }

        if(in_array('roleTypes', $optionalFields)){
            $job['roleTypes'] = [];
        }

        $fields = (string) $jobContent->div;

        $fieldsArray = explode("\n", $fields);

        foreach($fieldsArray as $field){
            $field = trim($field);
            $fieldNameEndPos = strpos($field, ':');

            if($fieldNameEndPos != false){
                $fieldName = substr($field, 0,  $fieldNameEndPos);

                if (in_array('id', $optionalFields)){
                    if($fieldName == 'Vacancy Id'){
                        $job['id'] = substr($field,  $fieldNameEndPos+1);
                    }
                }

                if (in_array('closingDate', $optionalFields)){
                    if($fieldName == 'Closing Date'){
                        $job['closingDate'] = preg_replace("/[^0-9]/", "", strtotime(substr($field,  $fieldNameEndPos+1)));
                    }
                }

                if (in_array('salary', $optionalFields)){
                    if($fieldName == 'Salary'){
                        $salary_validated = $this->validateSalary(substr($field, $fieldNameEndPos+1));
                
                        if (array_key_exists('min', $salary_validated)) {
                            $job['salaryMin'] = $salary_validated['min'];
                        }
            
                        if (array_key_exists('max', $salary_validated)) {
                            $job['salaryMax'] = $salary_validated['max'];
                        }
    
                        if (array_key_exists('london', $salary_validated)) {
                            $job['salaryLondon'] = $salary_validated['london'];
                        }
                    }
                }

                if (in_array('cities', $optionalFields)){
                    if($fieldName == 'Location'){
                        $job['cities'] = explode(",", substr($field,  $fieldNameEndPos+1));
                    }
                }

                if (in_array('roleTypes', $optionalFields)){
                    if($fieldName == 'Role Type'){
                        $job['roleTypes'] = explode(",", substr($field,  $fieldNameEndPos+1));
                    }
                }
            }

        }

        return $job;
        
    }

    function validateSalary($salary){

        $salary_validated = [];
        $salary = str_replace("-", " ", $salary); // replace dash with space
        $salary_range_array = explode(' ', $salary); //split by space, thereby catching all text but no numbers (assuming numbers don't have internal spaces)
        $salary_range_array = str_replace(".00", "", $salary_range_array); //strip occasional use of .00 (e.g. £34,500.00)
        $salary_range_array = preg_replace("/[^0-9]/", "", $salary_range_array); //strip all non-numerics
    
        $array_length = count($salary_range_array);
    
        for ($i=0; $i<=$array_length;$i++) { //loop through array removing elements less than 5 characters long
            if (isset($salary_range_array[$i]) && strlen($salary_range_array[$i])<5) unset($salary_range_array[$i]);
        }
    
        $salary_min = $salary_max = '';
        if (count($salary_range_array)) {
            $salary_min = min($salary_range_array);
            $salary_max = max($salary_range_array);
        }
    
        if ($salary_max == $salary_min) $salary_max = "";
    
        if (is_numeric($salary_min)) {
            $salary_validated['min'] = intval($salary_min);
        }
        if (is_numeric($salary_max)) {
            $salary_validated['max'] = intval($salary_max);
        }

        //London Waiting
        if (preg_match("/( .* London .* allowance of \£.*)/i", $salary)) { //London weighting string identified
            $london_weighting_allowance = preg_replace("/London .* allowance of \£/i","|||",$salary); //replace the regex string with a constant for splitting
            $london_weighting_allowance = explode("|||", $london_weighting_allowance)[1]; //split by start of the agreed term - take second element
            $london_weighting_allowance = explode(")", $london_weighting_allowance)[0]; //split by end of the agreed term - take first element
            $london_weighting_allowance = str_replace(".00", "", $london_weighting_allowance); //strip occasional use of .00 (e.g. £3,889.00)
            $london_weighting_allowance = preg_replace("/[^0-9]/", "", $london_weighting_allowance); //strip all non-numerics

            if (isset($london_weighting_allowance) && is_numeric($london_weighting_allowance)) {
                $salary_validated['london'] = intval($london_weighting_allowance);
            }
        }
    
        return $salary_validated;
    }
}