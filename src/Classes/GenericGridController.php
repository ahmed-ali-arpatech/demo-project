<?php

    namespace Leads\Classes;

    use App\Http\Controllers\Controller;
    use DateTime;
    use Carbon\Carbon;
    use function GuzzleHttp\Psr7\str;
    use Illuminate\Support\Collection;

    class GenericGridController extends Controller
    {
        // create criteria from url or form
        protected function createCriteriaFromUrl($param) {
            $criteria = rawurldecode($param);
            $criteria = json_decode($criteria, true);

            if($criteria == '') {
                return [];
            }

            if(isset($criteria['d']) && !in_array($criteria['d'], [0,1])) {
                unset($criteria['d']);
            }

            if(isset($criteria['o']) && $criteria['o'] == "") {
                unset($criteria['o']);
            }

            // change position because now our fs depends on disp
            $criteria['disp'] = $this->createDispFromUrl($criteria);
            $criteria['fs'] = $this->createFSFromUrl($criteria);


            return $criteria;
        }

        // create fs from url
        public function createFSFromUrl ($criteria) {

            if(!isset($criteria['fs'])) {
                return [];
            }

            // removed empty values from fs
            $result = array_filter($criteria['fs']);

            //$collection->contains('Desk');
            foreach ($result as $key => $value)
            {
                if (is_array($result[$key]))
                {
                    // range filter.
                    if(isset($value['start']) && isset($value['end'])) {
                        $result[$key]['start'] = $value['start'];
                        $result[$key]['end'] = $value['end'];
                    }
                    else
                    {
                        // filtered filter.
                        $result[$key] = array_values(array_filter($result[$key]));
                    }
                }
            }

            $fs = [];
            // reomved fs values if column not exist in disp
            foreach ($result as $key => $value)
            {
                if(!in_array($key, $criteria['disp']) && count($criteria['disp']) > 0) {
                    continue;
                }

                $fs[$key] = $value;
            }

            return array_filter($fs);
        }

        // create disp from url
        public function createDispFromUrl ($criteria)
        {
            if(!isset($criteria['disp'])) {
                return [];
            }

            $result = array_filter($criteria['disp']);
            return $result;
        }

        // create complete data here (header, filters, row, etc.)
        public function createTableData ($criteria, $responseData, $postData = "") {

            if($responseData['status_code'] == 422){
                abort(500, 'API RESPONSE 422');
            }

            //change label order according to entities
            $responseData=$this->changeLabelsOrders($responseData);

            // added Default columns in criteria
            $criteria['disp'] = (count($responseData['entities']) > 0) ? collect($responseData['entities'][0])->keys()->toArray() : $criteria['disp'];

            // set views data (not in use right now)
            $result['view'] = $this->createView();

            // set header columns
            $result['columnList'] = $this->createColumnList($responseData, $criteria, $postData);

            // get column count
            $result['columnCount'] = $this->columnCount($result['columnList']);

            // table data
            $result['dataSet'] = $this->createDataSet($responseData, $criteria);

            // total records
            $result['totalRecords'] = (!isset($responseData['pages']['total_records'])) ? 0 : $responseData['pages']['total_records'];

            // page no
            $result['currentPage'] = (!isset($responseData['pages']['page'])) ? 0 : $responseData['pages']['page'];

            if($result['currentPage'] > $result['totalRecords']) {
                $result['currentPage'] = 1;
            }

            // total pages
            $result['totalPages'] = (!isset($responseData['pages']['total_pages'])) ? 0 : $responseData['pages']['total_pages'];

            // record per page
            $result['numRecordsPerPage'] = (!isset($responseData['pages']['size'])) ? 0 : $responseData['pages']['size'];

            $result['pagination'] = collect([
                'pageStart' => ($result['currentPage'] == 1) ? 1 : ((($result['currentPage']-1)*$result['numRecordsPerPage'])+1),
                'pageEnd' => (($result['currentPage']*$result['numRecordsPerPage']) > $result['totalRecords']) ? $result['totalRecords'] : ($result['currentPage']*$result['numRecordsPerPage']),
                'totalRecords' => $result['totalRecords'],
                'numRecordsPerPage' => $result['numRecordsPerPage'],
                'currentPage' => $result['currentPage'],
                'totalPages' => $result['totalPages'],
            ])->toJson();

            // default date Filter Settings
            $result['dateFilterSettings'] = collect($this->dateFilterSettings())->toJson();

            // criteria send to blade
            $result['criteria'] = collect($criteria)->toJson();

            // column order by
            $criteria_o = (!isset($criteria['o'])) ? "" : $criteria['o'];
            $result['o'] = collect($criteria_o)->toJson();

            // column ascending or descending order
            $criteria_d = (!isset($criteria['d'])) ? "" : $criteria['d'];
            $result['d'] = collect($criteria_d)->toJson();

            return $result;
        }

        // create views for grid
        public function createView() {
            return collect(['options' => [], 'selected' => ''])->toJson();
        }

        // create columns header and filters for grid
        public function createColumnList($responseData, $criteria, $postData = "") {

            $result = collect($responseData['embedded']['labels'])->map(function ($items, $key) use ($criteria, $postData) {

                // filter type (filteredField, qtyField, dateField)
                $type = $items['type'];

                // Label text
                $title = $items['label'];

                // Column name (in database)
                $dataKey = $key;

                // Column data type (plain or linked text)
                $dataType = $items['data_type'];

                $column    = array();

                $column['type']      = $type;
                $column['title']     = $title;
                $column['dataKey']   = $dataKey;
                $column['dataType']  = $dataType;
                $column['is_default']  = (isset($items['is_default']) && $items['is_default']) ? true : false;

                $fs = (empty($criteria["fs"])) ? [] : $criteria["fs"];
                $disp = (empty($criteria["disp"])) ? true : $criteria["disp"];

                $column['filtered'] = isset($fs[$dataKey]);
                $column['selected']  = ($disp === true || in_array($dataKey, $criteria["disp"])) ? true : false;

                $column['sortValue'] = 0;
                if (isset($criteria['o']) && $criteria['o'] == $dataKey) {
                    $column['sortValue'] = ($criteria['d'] == 0) ? 2 : 1;
                }

                $dateData = $this->dateFilterSettings();

                if ($type == 'filteredField') {

                    $column['uniqueValues'] = [];
                    $column['filterValue'] = '';
                    $column['prePopulated'] = true;

                    // checked selected filter on page load
                    if(isset($fs) && array_key_exists($dataKey, $fs)) {

                        if(is_array($fs[$dataKey])) {
                            $column['uniqueValues'] =  collect($fs[$dataKey])->map(function ($value) {
                                return ['value' => $value, 'filter' => true];
                            })->unique('value');
                        }
                        else {
                            $column['filterValue'] =  $fs[$dataKey];
                        }
                    }
                }
                else if ($type == 'qtyField') {

                    $column['filterValue'] = '';
                    $column['range'] = ['start' => '', 'end' => ''];

                    if(isset($fs) && array_key_exists($dataKey, $fs))
                    {
                        $column['range'] = ['start' => $fs[$dataKey]['start'], 'end' => $fs[$dataKey]['end']];
                    }
                }
                else if ($type == 'dateField') {

                    if(!empty($postData))
                    {
                        foreach ($postData['columnList'] as $col)
                        {
                            if ($col['dataKey'] == $dataKey)
                            {
                                $dateData = $col['dateFilterSettings'];
                            }
                        }
                    }
                    $column['dateFilterSettings'] = $dateData;
                }

                $column['dateFilterSettings'] = $dateData;

                return collect($column);
            });

            // how many columns allow to show arrow column in right.
            // if values greater then this arrow columns shifts to left.
            $arrowColumnCount = 6;

            $arrow = collect([
                'type' => 'arrowField',
                'title' => '',
                'dataKey' => 'Arrow',
                'dataType' => 'arrowValue',
                'selected' => true,
                'sortValue' => 0,
                'alignmentClass' => (count($criteria['disp']) > $arrowColumnCount) ? 'arrow-column-alignment-left' : 'arrow-column-alignment-right'
            ]);

            // added ab extra empty index for action icons.
            if(count($criteria['disp']) > $arrowColumnCount) {
                $result->prepend($arrow);
            } else {
                $result->push($arrow);
            }

            // convert associative array to simple array
            return collect(array_values($result->toArray()))->tojson();
        }

        // Get Columns Count
        public function columnCount($columnList) {
            $columnCount = count(json_decode($columnList, true));
            return $columnCount;
        }

        function getDefaultColumns($labels){
            return collect($labels)->where('is_default', true)->keys()->toArray();
        }

        // create rows data for grid (makeGrid in markit place)
        public function createDataSet ($responseData, $criteria) {

            $linkedColumns = $this->getLinkedColumns($responseData['embedded']['labels'], $criteria);
            $possibleArrowLinkedColumns = $this->getPossibleArrowLinkedColumns($responseData['embedded']['labels'], $criteria);

            $result = collect($responseData['entities'])->map(function ($items) use ($linkedColumns, $possibleArrowLinkedColumns) {

                return collect($items)->map(function($column, $key){

                    // added column name condition here.
                    $result = $column;
                    if($key == 'body') {
                        $result = $column;
                    }

                    return $result;

                })
                ->put('Arrow', $this->generateArrowColumn($items, $possibleArrowLinkedColumns))
                ->put('columnLinks', $this->generateColumnLink($items, $linkedColumns))
                ->toArray();

            });

            return $result;
        }

        // get linked columns and skip which are not in criteria (disp).
        public function getLinkedColumns($allColumns, $criteria) {

            $dispColumns = $criteria['disp'];

            $linkedColumns = collect($allColumns)->reject(function($item, $key) use ($dispColumns) {
                // removed plain columns
                return ($item['data_type'] != 'linkedValue' || !in_array($key, $dispColumns));

            })->map(function($item, $key){
                return $key;
            })->toArray();

            return $linkedColumns;
        }

        // call that fetch linked column url from child controller.
        public function generateColumnLink($row, $linkedColumns)
        {
            $result = collect($linkedColumns)->map(function($item, $key) use ($row) {

                // functions should be in child controller
                return $this->getColumnLinkUrl($key, $row);

            })->toArray();

            return array_filter($result);
        }

        // get action menus and skip which are not in criteria (disp).
        public function getPossibleArrowLinkedColumns($allColumns, $criteria) {

            $dispColumns = $criteria['disp'];

            $possibleArrowLinkedColumns = collect($allColumns)->reject(function($item, $key) use ($dispColumns) {
                // removed plain columns
                return (!in_array($key, $dispColumns));

            })->map(function($item, $key){
                return $key;
            })->toArray();

            return $possibleArrowLinkedColumns;
        }

        // call that url link and pop up data from child controller.
        public function generateArrowColumn($row, $possibleArrowLinkedColumns)
        {
            $result = collect($possibleArrowLinkedColumns)->map(function($item, $key) use ($row) {

                // functions should be in child controller
                return $this->getArrowColumnLinkUrl($key, $row);

            })->toArray();

            // remove empty index.
            $result = array_filter($result);

            $allLinks = [];
            foreach ($result as $value)
            {
                if(count($value) > 1) {

                    foreach ($value as $value2)
                    {
                        $allLinks[] = $value2;
                    }

                } else
                {
                    $allLinks[] = $value[0];
                }
            }

            return $allLinks;
        }

        /* Ajax Function */

        // update drop down values direct ajax request.
        public function getFilteredFieldValues($field, $responseData, $criteria)
        {
            $oldCriteria = $this->createCriteriaFromUrl($criteria);

            $checkedArray = [];
            if(isset($oldCriteria['fs'][$field])) {
                $checkedArray = $oldCriteria['fs'][$field];
            }

            // get uniques values from specific column
            $uniqueValues =  collect($responseData['entities'])->reject(function($entities) use ($field) {

                return (trim($entities[$field]) == "");

            })->map(function ($entities) use ($field, $checkedArray) {

                $value = $entities[$field];

                $filter = ($value == $checkedArray) ? true : false;
                if(is_array($checkedArray)) {
                    $filter = (in_array($value, $checkedArray)) ? true : false;
                }

                return ['value' => $value, 'filter' => $filter];

            })->unique('value');


            return $uniqueValues;
        }

        // create fs from ajax request form
        public function createFSFromAjax ($columnList, $criteria = '') {

            $filterValue = $this->createFsFromAjaxForFilterValue($columnList);
            $qtyValue = $this->createFsFromAjaxForQtyValue($columnList);
            $dateValue = $this->createFsFromAjaxForDateValue($columnList);

            $result = array_filter(array_merge($filterValue, $qtyValue, $dateValue));

            $fs = [];
            // reomved fs values if column not exist in disp
            foreach ($result as $key => $value)
            {
                if(!in_array($key, $criteria['disp']) && count($criteria['disp']) > 0) {
                    continue;
                }

                $fs[$key] = $value;
            }

            return array_filter($fs);
        }

        // For input and checkbox filters.
        function createFsFromAjaxForFilterValue($columnList) {

            // for filterValue
            $filterValue = collect($columnList)->reject(function($value) {

                // removed non-filters column and empty values filter.
                return (!isset($value['uniqueValues']) || (count($value['uniqueValues']) < 1 && $value['filterValue'] == "" && $value['type'] != "filteredField"));

            })->map(function($value, $key){

                $columnName['dataKey'] = $value['dataKey'];

                // checked searched values first.
                if(trim($value['filterValue']) != "") {

                    $columnName['filter'] = $value['filterValue'];
                }

                if(trim($value['filterValue']) == "") {

                    $columnName['filter'] = collect($value['uniqueValues'])->reject(function ($value2) {

                        // removed not selected filters // trying to handle all possible cases just for safe side.
                        return ($value2['filter'] == "" || $value2['filter'] == 0 || $value2['filter'] == false || empty($value2['value']));

                    })->map(function ($value2) {

                        return $value2['value'];

                    })->toArray();
                }


                return $columnName;

            });

            $result = [];

            foreach ($filterValue->toArray() as $key => $value)
            {
                if(empty($value['filter'])) {
                    continue;
                }

                if(is_array($value['filter'])) {
                    $result[$value['dataKey']] = array_values($value['filter']);

                } else {
                    $result[$value['dataKey']] = $value['filter'];
                }
            }

            return $result;
        }

        // For qty filter
        function createFsFromAjaxForQtyValue($columnList) {

            $qtyFilter = collect($columnList)->reject(function($value) {

                // removed non-filters column and empty values filter.
                return ($value['type'] != "qtyField" || ($value['range']['start'] == "" && $value['range']['end'] == "" && $value['filterValue'] == ""));

            })->map(function($value, $key) {

                $columnName['dataKey'] = $value['dataKey'];

                // checked searched values first.
                if(trim($value['filterValue']) != "") {

                    $columnName['filter']['start'] = $value['filterValue'];
                    $columnName['filter']['end'] = $value['filterValue'];
                }

                if(trim($value['filterValue']) == "") {

                    $columnName['filter']['start'] = ($value['range']['start'] != "") ? $value['range']['start'] : $value['range']['end'];
                    $columnName['filter']['end'] = ($value['range']['end'] != "") ? $value['range']['end'] : $value['range']['start'];
                }

                return $columnName;
            })->toArray();

            $result = [];
            $qtyFilter = array_values(array_filter($qtyFilter));

            if(!empty($qtyFilter)) {
                foreach ($qtyFilter as $key => $value)
                {
                    if (empty($value['filter'])) {
                        continue;
                    }
                    $result[$value['dataKey']] = $value['filter'];
                }
            }

            return $result;
        }

        // For date filter
        function createFsFromAjaxForDateValue($columnList){

            $dateFilter = collect($columnList)->reject(function($value) {

                // removed non-filters column and empty values filter.
                return ($value['type'] != "dateField" || $value['dateFilterSettings']['timePeriod']['input'] == 'Select...' || ($value['dateFilterSettings']['fromUnit']['date'] == "" || $value['dateFilterSettings']['toUnit']['date'] == ""));

            })->map(function($value, $key) {

                $columnName['dataKey'] = $value['dataKey'];

                $columnName['filter']['start'] = $this->convertStringToDate($value['dateFilterSettings']['fromUnit']);
                $columnName['filter']['end'] = $this->convertStringToDate($value['dateFilterSettings']['toUnit']);
                // removed above lines because function should be transferred in api server.
                //$columnName['filter']['start'] = $value['dateFilterSettings']['fromUnit'];
                //$columnName['filter']['end'] = $value['dateFilterSettings']['toUnit'];

                return $columnName;

            })->toArray();

            $result = [];
            $dateFilter = array_values(array_filter($dateFilter));

            if(!empty($dateFilter)) {
                foreach ($dateFilter as $key => $value)
                {
                    if (empty($value['filter']) || $value['filter']['start'] == "" || $value['filter']['end'] == "" ) {
                        continue;
                    }
                    $result[$value['dataKey']] = $value['filter'];
                }
            }

            return $result;
        }

        // create date for filters.
        function convertStringToDate($column) {
            $column['startEndOptions']['option2'] = ($column['startEndOptions']['option2'] == "Day(s)") ? "day" : $column['startEndOptions']['option2'];
            $column['startEndOptions']['option2'] = ($column['startEndOptions']['option2'] == "Weeks(s)") ? "week" : $column['startEndOptions']['option2'];
            $column['startEndOptions']['option2'] = ($column['startEndOptions']['option2'] == "Years(s)") ? "year" : $column['startEndOptions']['option2'];
            $column['startEndOptions']['option2'] = ($column['startEndOptions']['option2'] == "Months(s)") ? "month" : $column['startEndOptions']['option2'];

            $option1 = strtolower($column['startEndOptions']['option1']);
            $option2 = strtolower($column['startEndOptions']['option2']);
            $option3 = strtolower($column['startEndOptions']['option3']);

            $dateString = $option1." ".$option2." ".$option3;

            if($column['input'] == "Today" || $column['input'] == "Yesterday" || $column['input'] == "Tomorrow") {
                $dateString = $column['input'];
            }
            if($column['input'] == "Specific Date"){
                // added this condition for mobile device
                if($column['date'] != "") {
                    $dateString =  date('Y-m-d',strtotime($column['date']));
                }
            }
            $dateString = strtolower(str_replace(' ', '', $dateString));
            return $dateString;
        }
        // create disp from ajax request form
        public function createDispFromAjax ($columnList) {

            $dispColumns = collect($columnList)->reject(function($item) {

                // removed non-filters column and empty values filter.
                return ($item['selected'] != 1);

            })->map(function($item, $key){

                return $item['dataKey'];
            })->toArray();

            return array_values($dispColumns);
        }

        // create criteria from url or form
        protected function createCriteriaFromForm($requestAll) {

            $columnList = $requestAll['columnList'];
            $criteria['disp'] = $this->createDispFromAjax($columnList);
            $criteria['fs'] = $this->createFSFromAjax($columnList, $criteria);

            if (is_array($requestAll['d']) && $requestAll['d'][0] != "") {
                $criteria['d'] = $requestAll['d'][0];
            }
            else if (!is_array($requestAll['d']) && $requestAll['d'] != "") {
                $criteria['d'] = $requestAll['d'];
            }

            if (is_array($requestAll['o']) && $requestAll['o'][0] != "") {
                $criteria['o'] = $requestAll['o'][0];
            }
            // get string in sort request.
            else if (!is_array($requestAll['o']) && $requestAll['o'] != "") {
                $criteria['o'] = $requestAll['o'];
            }

            // url-Decoding values
            if (isset($criteria['fs']) && !empty($criteria['fs'])) {
                foreach ($criteria['fs'] as $key => $fs) {
                    if (is_array($fs)) {
                        foreach ($fs as $i => $f) {
                            $criteria['fs'][$key][$i] = rawurldecode($f);
                        }
                    } else {
                        $criteria['fs'][$key] = rawurldecode($fs);
                    }
                }
            }

            return $criteria;
        }

        // create table from ajax request
        public function createTableDataForAjax ($criteria, $responseData, $postData = "") {

            $createTableData = $this->createTableData($criteria, $responseData, $postData);

            $result['view'] = json_decode($createTableData['view']);
            $result['columnList'] = json_decode($createTableData['columnList']);
            $result['columnCount'] = $createTableData['columnCount'];
            $result['dataSet'] = json_decode($createTableData['dataSet']);
            $result['totalRecords'] = $createTableData['totalRecords'];
            $result['currentPage'] = $createTableData['currentPage'];
            $result['totalPages'] = $createTableData['totalPages'];
            $result['numRecordsPerPage'] = $createTableData['numRecordsPerPage'];
            $result['pagination'] = json_decode($createTableData['pagination']);
            $result['dateFilterSettings'] = json_decode($createTableData['dateFilterSettings']);
            $result['criteria'] = ($createTableData['criteria']);
            $result['o'] = json_decode($createTableData['o'], true);
            $result['d'] = json_decode($createTableData['d'], true);

            return $result;
        }

        // date filter html
        function dateFilterSettings()
        {
            $default = [
                'dataFrom' => '0', // 0 = on load, 1 = ajax
                'timePeriod' => ['input' => 'Select...'],
                'fromUnit' => [
                    'input' => 'Select...',
                    'date' => date('m/d/Y'),
                    'numberOfDays' => '',
                    'startEndOptions' => [
                        'option1' => 'Start of',
                        'option2' => 'This',
                        'option3' => 'Month',
                    ],
                    'dateString' => date('D M d Y H:i:s O'),//'2015-01-25T06:00:00.000Z'
                ],
                'toUnit' => [
                    'input' => 'Select...',
                    'date' => date('m/d/Y'),
                    'numberOfDays' => '',
                    'startEndOptions' => [
                        'option1' => 'End of',
                        'option2' => 'This',
                        'option3' => 'Month',
                    ],
                    'dateString' => date('D M d Y H:i:s O'),//'2022-09-24T06:00:00.000Z'
                ]
            ];
            return $default;

        }
        // sort labels array according to entities orders
        public function changeLabelsOrders($responseData) {
            if (!empty($responseData['entities'][0])) {
                $mapResultWithEntities = [];
                $remaininglabels = [];

                foreach ($responseData['entities'][0] as $entities => $value) {
                    if (isset($responseData['embedded']['labels'])) {
                        foreach ($responseData['embedded']['labels'] as $key => $value) {

                            if ($entities==$key) {
                                $mapResultWithEntities[$key] = $responseData['embedded']['labels'][$key];
                            } else {
                                $remaininglabels[$key] = $responseData['embedded']['labels'][$key];

                            }

                        }
                    }
                }

                $responseData['embedded']['labels']= array_merge($mapResultWithEntities,$remaininglabels);
            }

            return $responseData;
        }
    }
