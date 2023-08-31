<?php


class OleeoFeedParser {

    private $artificial_role_types = [
        "Prison Catering",
        "Case Administrator",
        "Community Payback",
        "Youth Justice Worker",
        "Probation Service Officer"
    ];
    private $feedType = 'complex';
    private $filters = [];
    private $optionalFields = [];
    private $newJobArray = [];

    private $optionalFieldsComplex = [
        [
            'jsonKey' => 'id',
            'propName' => 'Vacancy ID',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'availablePositions',
            'propName' => 'Number of positions available',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'organisation',
            'propName' => 'Organisation',
            'type' => 'organisation'
        ],
        [
            'jsonKey' => 'closingDate',
            'propName' => 'Closing Date',
            'type' => 'date'
        ],
        [
            'jsonKey' => 'salary',
            'propName' => 'Salary Minimum',
            'type' => 'salary'
        ],
        [
            'jsonKey' => 'salaryRange',
            'propName' => 'Salary Range',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'addresses',
            'propName' => 'Building/Site',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'cities',
            'propName' => 'City/Town',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'regions',
            'propName' => 'Geographical Region(s)',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'roleTypes',
            'propName' => 'Role Type',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'contractTypes',
            'propName' => 'Working Pattern',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'desc',
            'propName' => 'Job description Additional Information',
            'type' => 'html'
        ],
        [
            'jsonKey' => 'additionalInfo',
            'propName' => 'Additional Information',
            'type' => 'html'
        ]
    ];

    private $optionalFieldsSimple = [
        [
            'jsonKey' => 'id',
            'propName' => 'Vacancy Id',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'closingDate',
            'propName' => 'Closing Date',
            'type' => 'date'
        ],
        [
            'jsonKey' => 'salary',
            'propName' => 'Salary',
            'type' => 'salary'
        ],
        [
            'jsonKey' => 'cities',
            'propName' => 'Location',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'roleTypes',
            'propName' => 'Role Type',
            'type' => 'array'
        ]
    ];

    /** 
    * Converts XML File to JSON FIle
    * @param string $sourceFile Source XML File to be parsed
    * @param string $outputFile JSON File that will be created
    * @param array $optionalFields 
    * @param string $feedType
    * @return array $parseResult 
    **/
    public function parseToJSON(string $sourceFile, string $outputFile, array $optionalFields = [], $feedType = 'complex', $filters = []){

        $this->feedType = $feedType;
        $this->filters = $filters;

        //reset to default value
        $this->newJobArray = [];
        $this->optionalFields = [];
        
        $this->setOptionalFields($optionalFields);

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
                
                $newJob = $this->validateJobDetails($xmlContent->entry[$x]);

                $newJob = $this->matchWithFilters($newJob);

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

    function setOptionalFields($optionalFields){
        if(empty($optionalFields)){
            return;
        }

        if ($this->feedType == 'simple'){
            $fields = $this->optionalFieldsSimple;
        }
        else {
            $fields = $this->optionalFieldsComplex;
        }

        foreach($fields as $field){
            if(!in_array($field['jsonKey'], $optionalFields)){
                continue;
            }
            $this->optionalFields[] = $field;

            if($field['type'] == 'array'){
                $this->newJobArray[$field['jsonKey']] = [];
            }
            else if($field['type'] == 'salary'){
                $this->newJobArray['salaryMin'] = '';
                $this->newJobArray['salaryMax'] = '';
                $this->newJobArray['salaryLondon'] = '';
            }
            else {
                $this->newJobArray[$field['jsonKey']] = '';
            }
        }
    }

    function matchWithFilters($job){

        if(empty($this->filters)){
            return $job;
        }

        foreach($this->filters as $filter){

            //if fieldName and acceptedValues not set we cant validated filter - so skip filter
            if(!array_key_exists('fieldName', $filter) || !array_key_exists('acceptedValues', $filter) || empty($filter['fieldName']) || empty($filter['acceptedValues'])){
                continue;
            }

            $fieldName = $filter['fieldName'];

            if(is_array($filter['acceptedValues'])){
                $acceptedValues = $filter['acceptedValues'];
            }
            else {
                $acceptedValues = [$filter['acceptedValues']];
            }

            //checks if current job has field and its not empty
            if(!array_key_exists($fieldName, $job) || empty($job[$fieldName])){
                return false;
            }

            $valueFound = false;

            foreach($acceptedValues as $acceptedValue){

                //decide if field is array fields e.g. cities or a string e.g. organisation
                if(is_array($job[$fieldName])){
                    if(in_array($acceptedValue, $job[$fieldName])){
                        $valueFound = true;
                        break;
                    }
                }
                else {
                    if($acceptedValue == $job[$fieldName]){
                        $valueFound = true;
                        break;
                    }
                }
            }

            //if value not found no need to check other filters
            if(!$valueFound){
                return false;
            }
        }

        return $job;
    }

    function validateJobDetails($jobXML){
        
        $job = $this->newJobArray;
        $jobTitle = (string) $jobXML->title; 
        $jobURL = (string) $jobXML->id; 
        $jobContent = $jobXML->content;

        $job['title'] = trim($jobTitle);
        $job['url'] = $jobURL;
        
        // Job title and Job URL are required
        if (empty($jobTitle) || empty($jobURL)){
            return false;
        }

        if ($this->feedType == 'simple'){
            $job = $this->validateOptionalFieldsbyNewLine($job, $jobContent);
        }
        else {
            $job = $this->validateOptionalFieldsbySpan($job, $jobContent);
        }

        //Strip Vacancy ID from title
        if(array_key_exists('id', $job) && !empty($job['id'])){

            //encoding html entities as preg_repalce was failing with special characters e.g. right single quote
            //decoding first because some titles have decoded html already e.g. &amp;
            $newTitle = preg_replace("/^" . $job['id'] . "\s*[-]/", "", htmlentities(html_entity_decode($job['title'])));
            $newTitle = preg_replace("/^" . $job['id'] . "\s*&ndash;/", "", $newTitle);
        
            //further title cleaning that uses ':' - eg. prison officer campaign numbers, hmpps jobs
            $newTitle = preg_replace("/^(\d+|C|SSF|SSO)\s*:/", "", trim($newTitle));

            if(!empty($newTitle)){
               $job['title'] = html_entity_decode(trim($newTitle));
            }
        }

        return $job;
    }

    function validateOptionalFieldsbySpan($job, $jobContent){

        $totalSpans = count($jobContent->div->span);

        //loops through span elements to find job details by matching to itemprop value
        for ($y = 0; $y < $totalSpans; $y++) {

            $currentSpan = $jobContent->div->span[$y];
            $itemProp = $currentSpan->attributes()->itemprop;

            foreach($this->optionalFields as $optionalField){

                if($optionalField['propName'] != $itemProp){
                    continue;
                }

                $jsonKey = $optionalField['jsonKey'];


                if($optionalField['type'] == 'array'){
                    array_push($job[$jsonKey], (string) $currentSpan);
                }
                else if($optionalField['type'] == 'date'){
                    $job[$jsonKey] = preg_replace("/[^0-9]/", "", strtotime((string) $currentSpan));
                }
                else if($optionalField['type'] == 'html'){
                    $job[$jsonKey] = (string) $currentSpan->asXML();
                }
                else if($optionalField['type'] == 'salary'){
                    $salary_validated = $this->validateSalary((string) $currentSpan);
        
                    if (array_key_exists('min', $salary_validated)) {
                        $job['salaryMin'] = (string) $salary_validated['min'];
                    }
        
                    if (array_key_exists('max', $salary_validated)) {
                        $job['salaryMax'] = (string) $salary_validated['max'];
                    }

                    if (array_key_exists('london', $salary_validated)) {
                        $job['salaryLondon'] = (string) $salary_validated['london'];
                    }
                }
                else if($optionalField['type'] == 'organisation'){
                    $job[$jsonKey] = trim(str_replace('AGY -', '', (string) $currentSpan));
                }
                else {
                    //Is string field type

                    $job[$jsonKey] = (string) $currentSpan;

                }
            }
        }

        foreach ($this->artificial_role_types as $job_type) {
            if(strpos("x".$job['title'], $job_type)) {
                array_push($job['roleTypes'], (string) $job_type);
                continue;
            }
        }

        return $job;
    }

    function validateOptionalFieldsbyNewLine($job, $jobContent){

        if(in_array('cities', $this->optionalFields)){
            $job['cities'] = [];
        }

        if(in_array('roleTypes', $this->optionalFields)){
            $job['roleTypes'] = [];
        }

        foreach ($this->artificial_role_types as $job_type) {
            if(strpos("x".$job['title'], $job_type)) {
                // strpos returns false if the "needle" is at position zero in the "haystack",
                // so we add a character to the beginning to ensure that's not going to happen.
                array_push($job['roleTypes'], (string) $job_type);
                continue;
            }
        }

        $fields = (string) $jobContent->div;

        $fieldsArray = explode("\n", $fields);

        foreach($fieldsArray as $field){
            $field = trim($field);
            $fieldNameEndPos = strpos($field, ':');
          
            if($fieldNameEndPos != false){
                $fieldName = substr($field, 0,  $fieldNameEndPos);
                $fieldValue = substr($field,  $fieldNameEndPos+1);

                if(empty($fieldValue)){
                    continue;
                }

                foreach($this->optionalFields as $optionalField){

                    if($optionalField['propName'] != $fieldName){
                        continue;
                    }

                    $jsonKey = $optionalField['jsonKey'];

                    if($optionalField['type'] == 'array'){
                        $job[$jsonKey] = explode(",", $fieldValue);
                    }
                    else if($optionalField['type'] == 'date'){
                        $job[$jsonKey] = preg_replace("/[^0-9]/", "", strtotime($fieldValue));
                    }
                    else if($optionalField['type'] == 'salary'){
                        $salary_validated = $this->validateSalary($fieldValue);
                
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
                    else {
                        $job[$jsonKey] = $fieldValue;
                    }
                }

                if (in_array('salary', $this->optionalFields)){
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
            
            }

        }

        return $job;
        
    }

    function validateSalary($salary){

        $salary_validated = [];

        if($salary == "Unpaid"){
            $salary_validated = [
                'min' => 'Unpaid',
                'max' => 'Unpaid',
            ];

            return $salary_validated;
        }

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