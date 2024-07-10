<?php


class AvatureFeedParser {

    private $artificial_role_types = [
        "Prison Catering",
        "Case Administrator",
        "Community Payback",
        "Youth Justice Worker",
        "Probation Services Officer"
    ];

    private $filters = [];
    private $optionalFields = [];
    private $newJobArray = [];

    private $feedFields = [
        [
            'jsonKey' => 'id',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'availablePositions',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'grade',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'organisation',
            'type' => 'organisation'
        ],
        [
            'jsonKey' => 'businessGroup',
            'type' => 'string'
        ],
        [
            'jsonKey' => 'startDate',
            'type' => 'date',
            'time' => array('hour' => 0, 'minute' => 0 , 'second' => 0 , 'microsecond' => 0)
        ],
        [
            'jsonKey' => 'closingDate',
            'type' => 'date',
            'time' => array('hour' => 23, 'minute' => 55 , 'second' => 0 , 'microsecond' => 0)
        ],
        [
            'jsonKey' => 'salaryMin',
            'type' => 'salary'
        ],
        [
            'jsonKey' => 'salaryMax',
            'type' => 'salary'
        ],
        [
            'jsonKey' => 'salaryLondonWeighting',
            'type' => 'salary'
        ],
        [
            'jsonKey' => 'salaryRange',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'addresses',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'cities',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'regions',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'roleTypes',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'contractTypes',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'prisonNames',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'prisonTypes',
            'type' => 'array'
        ],
        [
            'jsonKey' => 'prisonCategory',
            'type' => 'array'
        ]
    ];

    /**
    * Replaces certain phrases in the job title
    */
    public function fixJobTitleTypos($title) {
        $typos = [
            ["Probation Service Officer","Probation Services Officer"]
        ];
        foreach ($typos as $typo) {
            $title = str_replace($typo[0],$typo[1],$title);
        }
        return $title;
    }

    /** 
    * Converts Avature JSON Data to JSON File
    * @param string $jsonData JSON data to be parsed
    * @param string $outputFile JSON File that will be created
    * @param array  $optionalFields 
    * @return array $parseResult 
    **/
    public function parse(object $jsonData, string $outputFile, array $optionalFields = [], $filters = []){

        $parseResult = ['success' => false, 'errors' => [] ];

        if(!property_exists($jsonData, 'jobs') || !is_array($jsonData->jobs)) {
            echo 'Jobs property not found';
            return $parseResult;
        }

        $this->filters = $filters;

        //reset to default value
        $this->newJobArray = [];
        $this->optionalFields = [];
        
        $this->setOptionalFields($optionalFields);

        $jobs = [];
        $totalJobs = count($jsonData->jobs);

        if ($totalJobs > 0){

            foreach($jsonData->jobs as $jobData){

                 $newJob = $this->validateJobDetails($jobData);

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
             echo 'Error parsing - encoding';
            $parseResult['errors'][] = "Error Parsing Feed [sourceFile: $sourceFile] - Error encoding feed data";
        }
        
        $writeFileResult = file_put_contents($outputFile, $outputJSON);

        if($writeFileResult === false){
               echo 'Error parsing - writing';
            $parseResult['errors'][] = "Error Parsing Feed [sourceFile: $sourceFile] - Output file failed to write [outputFile: $outputFile]";
        }
        else {
            echo 'Success woohoo';
            $parseResult['success'] = true;
        }
        
        return $parseResult;
        
    }

    function setOptionalFields($optionalFields){
        if(empty($optionalFields)){
            return;
        }

        $fields = $this->feedFields;

        foreach($fields as $field){
            if(!in_array($field['jsonKey'], $optionalFields)){
                continue;
            }
            $this->optionalFields[] = $field;

            if($field['type'] == 'array'){
                $this->newJobArray[$field['jsonKey']] = [];
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

    function validateJobDetails($jobData){
        
        $job = $this->newJobArray;
        
        $jobTitle = $jobData->title; 
        $jobURL = $jobData->jobURL; 

        $job['title'] = trim($jobTitle);
        $job['url'] = $jobURL;
        
        // Job title and Job URL are required
        if (empty($jobTitle) || empty($jobURL)){
            return false;
        }

        $job = $this->validateOptionalFields($job, $jobData);

        //Title fixes and functions

        $job['title'] = $this->fixJobTitleTypos($job['title']);

        //Strip Vacancy ID from title
        $job['title'] = $this->tidyTitle($job['title']);

        $job = $this->applyArtificialRoles($job);
        
        return $job;
    }

    function tidyTitle($title){

        $newTitle = preg_replace("/^[0-9]+\s*[-]/", "", htmlentities(html_entity_decode($title)));

        $newTitle = preg_replace("/^(\d+|C|SSF|SSO)\s*:/", "", trim($newTitle));

        if(!empty($newTitle)){
            $title= html_entity_decode(trim($newTitle));
        }
        
        return $title;
    }

    function validateOptionalFields($job, $jobData){

        foreach($this->optionalFields as $optionalField){

                $jsonKey = $optionalField['jsonKey'];

                if(!property_exists($jobData, $jsonKey)) {
                    continue;
                }

                $fieldValue = $jobData->$jsonKey;

                if($optionalField['type'] == 'date'){

                    $date = DateTime::createFromFormat('d/m/Y', $fieldValue);

                    if ($date === false) {
                        continue;
                    }

                    $time = $optionalField['time'];

                    if(!empty($time)){
                        $date = $date->setTime($time['hour'], $time['minute'], $time['second'], $time['microsecond']);
                    }
                    else {
                        $date = $date->setTime(0,0,0,0);
                    }

                    $job[$jsonKey] = (string) $date->getTimestamp();
                
                }
                else if($optionalField['type'] == 'salary'){

                    if($fieldValue == "Unpaid"){
                        $job[$jsonKey] = $fieldValue;
                    }
                    else {
                        $job[$jsonKey] = preg_replace("/[^0-9]/", "", $fieldValue);
                    }
                }
                else if($optionalField['type'] == 'organisation'){
                    $job[$jsonKey] = trim(str_replace('AGY -', '', $fieldValue));
                }
                else {

                    //used for array and string
                    $job[$jsonKey] = $fieldValue;

                }


        }
        
        return $job;
    }

    function applyArtificialRoles($job){

        foreach ($this->artificial_role_types as $job_type) {
            if(strpos("x".$job['title'], $job_type)) {
                array_push($job['roleTypes'], (string) $job_type);
                continue;
            }
        }

        return $job;
    }
}
