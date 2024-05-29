<?php
// add filters and filter csv-files
include_once('csvprocessor.php');
include_once("../libraries/xlsxwriter.class.php");

class CSVFILTER extends API {
    // processed parameters for readability
    public $_requestedMethod = REQUEST[1];
    private $_requestedID = null;

	public function __construct(){
		parent::__construct();
		if (!array_key_exists('user', $_SESSION)) $this->response([], 401);

		$this->_requestedID = $this->_requestedID = array_key_exists(2, REQUEST) ? REQUEST[2] : null;
	}

	/**
	 * gets and posts filter rules
	 * no putting though for audit safety
	 */
	public function rule(){
		if (!$this->permissionFor('csvrules')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$filter = [
					':name' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('csvfilter.edit_filter_name')),
					':author' => $_SESSION['user']['name'],
					':content' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('csvfilter.edit_filter_content')),
					':hidden' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('csvfilter.edit_filter_hidden')) === LANG::PROPERTY('csvfilter.edit_filter_hidden_hidden')? 1 : 0,
				];

				if (!trim($filter[':name']) || !trim($filter[':content'])) $this->response([], 400);

				// ensure valid json for filters
				if ($filter[':content'] && !json_decode($filter[':content'], true))  $this->response(['status' => ['msg' => LANG::GET('csvfilter.edit_filter_content_hint'), 'type' => 'error']]);

				// put hidden attribute if anything else remains the same
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter_get-latest-by-name'));
				$statement->execute([
					':name' => $filter[':name']
				]);
				$exists = $statement->fetch(PDO::FETCH_ASSOC);
				if ($exists && $exists['content'] === $filter[':content']) {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter-put'));
					if ($statement->execute([
						':hidden' => $filter[':hidden'],
						':id' => $exists['id']
						])) $this->response([
							'status' => [
								'name' => $filter[':name'],
								'msg' => LANG::GET('csvfilter.edit_filter_saved', [':name' => $filter[':name']]),
								'type' => 'success'
							]]);	
				}

				foreach(INI['forbidden']['names'] as $pattern){
					if (preg_match("/" . $pattern . "/m", $filter[':name'], $matches)) $this->response(['status' => ['msg' => LANG::GET('csvfilter.error_forbidden_name', [':name' => $filter[':name']]), 'type' => 'error']]);
				}

				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter-post'));
				if ($statement->execute($filter)) $this->response([
						'status' => [
							'name' => $filter[':name'],
							'msg' => LANG::GET('csvfilter.edit_filter_saved', [':name' => $filter[':name']]),
							'type' => 'success'
						]]);
				else $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('csvfilter.edit_filter_not_saved'),
						'type' => 'error'
					]]);
				break;
			case 'GET':
				$filterdatalist = [];
				$options = ['...' . LANG::GET('csvfilter.edit_filter_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$alloptions = ['...' . LANG::GET('csvfilter.edit_filter_new') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$return = [];

				// get selected filter
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter_get-filter'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter_get-latest-by-name'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if (!$filter = $statement->fetch(PDO::FETCH_ASSOC)) $filter = [
					'id' => '',
					'name' => '',
					'content' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$filter['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('csvfilter.error_filter_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing filter lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter-datalist'));
				$statement->execute();
				$filters = $statement->fetchAll(PDO::FETCH_ASSOC);
				$hidden = [];
				$dependedtemplates = [];
				foreach($filters as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden)) {
						$filterdatalist[] = $row['name'];
						$options[$row['name'] ] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
					$alloptions[$row['name']. ' ' . LANG::GET('assemble.compose_component_author', [':author' => $row['author'], ':date' => $row['date']])] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
				}

				$return['body'] = [
					'form' => [
						'data-usecase' => 'csvfilter',
						'action' => "javascript:api.csvfilter('post', 'rule')"],
					'content' => [
						[
							[
								[
									'type' => 'datalist',
									'content' => $filterdatalist,
									'attributes' => [
										'id' => 'filters'
									]
								], [
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('csvfilter.edit_filter_select'),
										'onchange' => "api.csvfilter('get', 'rule', this.value)"
									],
									'content' => $options
								], [
									'type' => 'searchinput',
									'attributes' => [
										'name' => LANG::GET('csvfilter.edit_filter'),
										'list' => 'filters',
										'onkeypress' => "if (event.key === 'Enter') {api.csvfilter('get', 'rule', this.value); return false;}"
									]
								]
							], [
								[
									'type' => 'select',
									'attributes' => [
										'name' => LANG::GET('csvfilter.edit_filter_all'),
										'onchange' => "api.csvfilter('get', 'rule', this.value)"
									],
									'content' => $alloptions
								]
							]
						], [
							[
								'type' => 'textinput',
								'attributes' => [
									'name' => LANG::GET('csvfilter.edit_filter_name'),
									'value' => $filter['name'],
									'required' => true,
									'data-loss' => 'prevent'
								]
							], [
								'type' => 'textarea',
								'hint' => LANG::GET('csvfilter.edit_filter_content_hint'),
								'attributes' => [
									'name' => LANG::GET('csvfilter.edit_filter_content'),
									'value' => $filter['content'],
									'rows' => 16,
									'id' => 'content',
									'required' => true,
									'data-loss' => 'prevent'
								]
							]
						]
					]
				];
				if ($filter['id']){
					$hidden = [
						'type' => 'radio',
						'attributes' => [
							'name' => LANG::GET('csvfilter.edit_filter_hidden')
						],
						'content' => [
							LANG::GET('csvfilter.edit_filter_hidden_visible') => ['checked' => true],
							LANG::GET('csvfilter.edit_filter_hidden_hidden') => []
						],
						'hint' => LANG::GET('csvfilter.edit_filter_hidden_hint')
					];
					if ($filter['hidden']) $hidden['content'][LANG::GET('csvfilter.edit_filter_hidden_hidden')]['checked'] = true;
					array_push($return['body']['content'][1], $hidden);
				}
				$this->response($return);
				break;
		}					
	}

	/**
	 * get responds with form to select and apply filter
	 * post responds with a download link to the result file after processing
	 */
	public function filter(){
		if (!$this->permissionFor('csvfilter')) $this->response([], 401);
		switch ($_SERVER['REQUEST_METHOD']){
			case 'POST':
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter_get-filter'));
				$statement->execute([
					':id' => $this->_requestedID
				]);
				$filter = $statement->fetch(PDO::FETCH_ASSOC);

				if (!$filter) $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('csvfilter.error_filter_not_found'),
						'type' => 'error'
					]]);
				$content = json_decode($filter['content'], true);

				$inputfile = array_key_exists(LANG::PROPERTY('csvfilter.use_filter_input_file'), $_FILES) ? $_FILES[LANG::PROPERTY('csvfilter.use_filter_input_file')]['tmp_name'] : null;
				if (!$inputfile) $this->response([
					'status' => [
						'name' => false,
						'msg' => LANG::GET('csvfilter.use_filter_no_input_file'),
						'type' => 'error'
					]]);
				$content['filesetting']['source'] = $inputfile;
				if (!array_key_exists('dialect', $content['filesetting'])) $content['filesetting']['dialect'] = INI['csv']['dialect'];
				$content['filesetting']['encoding'] = INI['likeliness']['csvprocessor_source_encoding'];

				$comparefileindex = 0;
				foreach($content['filter'] as &$filtertype){
					if ($filtertype['apply'] === 'filter_by_comparison_file' && $filtertype['filesetting']['source'] !== 'SELF') {
						$comparefile = array_key_exists(LANG::PROPERTY('csvfilter.use_filter_compare_file'), $_FILES) && array_key_exists($comparefileindex, $_FILES[LANG::PROPERTY('csvfilter.use_filter_compare_file')]['tmp_name']) ? $_FILES[LANG::PROPERTY('csvfilter.use_filter_compare_file')]['tmp_name'][$comparefileindex] : null;
						if (!$comparefile) $this->response([
							'status' => [
								'name' => false,
								'msg' => LANG::GET('csvfilter.use_filter_no_compare_file', [':name' => $filtertype['filesetting']['source']]),
								'type' => 'error'
							]]);
						$filtertype['filesetting']['source'] = $comparefile;
						$comparefileindex++;
					}
				}
				
				$datalist = new Listprocessor($content, [
					'processedMonth' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('csvfilter.use_filter_month')),
					'processedYear' => UTILITY::propertySet($this->_payload, LANG::PROPERTY('csvfilter.use_filter_year'))
				]);

				// clear up tmp folder
				UTILITY::tidydir('tmp', INI['lifespan']['tmp']);

				//create and write to file
				$downloadfiles=[];
				switch (strtolower(pathinfo($content['filesetting']['destination'])['extension'])){
					case 'csv':
						foreach($datalist->_list as $subsetname => $subset){
							if (intval($subsetname)) $subsetname = pathinfo($content['filesetting']['destination'])['filename'];
							$tempFile = UTILITY::directory('tmp') . '/' . time() . $subsetname . '.csv';
							$file = fopen($tempFile, 'w');
							fwrite($file, b"\xEF\xBB\xBF"); // tell excel this is utf8
							fputcsv($file, $datalist->_setting['filesetting']['columns'],
								$datalist->_setting['filesetting']['dialect']['separator'],
								$datalist->_setting['filesetting']['dialect']['enclosure'],
								$datalist->_setting['filesetting']['dialect']['escape']);
							foreach($subset as $line) {
								fputcsv($file, $line,
								$datalist->_setting['filesetting']['dialect']['separator'],
								$datalist->_setting['filesetting']['dialect']['enclosure'],
								$datalist->_setting['filesetting']['dialect']['escape']);
							}
							fclose($file);
							$downloadfiles[LANG::GET('csvfilter.use_filter_download', [':file' => preg_replace('/.csv$/', (count($datalist->_list) > 1 ? '_' . $subsetname. '.csv' : '.csv'), $content['filesetting']['destination'])])] = [
								'href' => substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
								'download' => preg_replace('/.csv$/', (count($datalist->_list) > 1 ? '_' . $subsetname. '.csv' : '.csv'), $content['filesetting']['destination'])
							];
						}
						break;
					case 'xls': // do nothing, let xlsx catch
					case 'xlsx':
						$tempFile = UTILITY::directory('tmp') . '/' . time() . '.xlsx';
						$writer = new XLSXWriter();
						$writer->setAuthor($_SESSION['user']['name']); 
						foreach($datalist->_list as $subsetname => $subset){
							if (intval($subsetname)) $subsetname = pathinfo($content['filesetting']['destination'])['filename'];
							$writer->writeSheetRow($subsetname, $datalist->_setting['filesetting']['columns']);
							foreach ($subset as $line)
								$writer->writeSheetRow($subsetname, $line);
						}
						$writer->writeToFile($tempFile);
						$content['filesetting']['destination'] = preg_replace('/.xls$/', '.xlsx', $content['filesetting']['destination']);
						$downloadfiles[LANG::GET('csvfilter.use_filter_download', [':file' => $content['filesetting']['destination']])] = [
							'href' => substr(UTILITY::directory('tmp'), 1) . '/' . pathinfo($tempFile)['basename'],
							'download' => $content['filesetting']['destination']
						];
						break;
				}
				
				$this->response([
					'log' => $datalist->_log,
					'links' => $downloadfiles
				]);
				break;
			case 'GET':
				$filterdatalist = [];
				$options = ['...' . LANG::GET('csvfilter.use_filter_select') => (!$this->_requestedID) ? ['value' => '0', 'selected' => true] : ['value' => '0']];
				$return = [];

				// get selected template
				if (intval($this->_requestedID)){
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter_get-filter'));
					$statement->execute([
						':id' => $this->_requestedID
					]);
				} else {
					$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter_get-latest-by-name'));
					$statement->execute([
						':name' => $this->_requestedID
					]);
				}
				if (!$filter = $statement->fetch(PDO::FETCH_ASSOC)) $filter = [
					'id' => '',
					'name' => '',
					'content' => ''
				];
				if($this->_requestedID && $this->_requestedID !== 'false' && !$filter['name'] && $this->_requestedID !== '0') $return['status'] = ['msg' => LANG::GET('csvfilter.error_template_not_found', [':name' => $this->_requestedID]), 'type' => 'error'];
		
				// prepare existing templates lists
				$statement = $this->_pdo->prepare(SQLQUERY::PREPARE('csvfilter-datalist'));
				$statement->execute();
				$filters = $statement->fetchAll(PDO::FETCH_ASSOC);
				$hidden = [];
				foreach($filters as $key => $row) {
					if ($row['hidden']) $hidden[] = $row['name']; // since ordered by recent, older items will be skipped
					if (!array_key_exists($row['name'], $options) && !in_array($row['name'], $hidden)) {
						$filterdatalist[] = $row['name'];
						$options[$row['name']] = ($row['name'] == $filter['name']) ? ['value' => $row['id'], 'selected' => true] : ['value' => $row['id']];
					}
				}

				$return['body'] = [
					'content' => [
						[
							[
								'type' => 'datalist',
								'content' => $filterdatalist,
								'attributes' => [
									'id' => 'filters'
								]
							], [
								'type' => 'select',
								'attributes' => [
									'name' => LANG::GET('csvfilter.use_filter_select'),
									'onchange' => "api.csvfilter('get', 'filter', this.value)"
								],
								'content' => $options
							], [
								'type' => 'searchinput',
								'attributes' => [
									'name' => LANG::GET('csvfilter.edit_filter'),
									'list' => 'filters',
									'onkeypress' => "if (event.key === 'Enter') {api.csvfilter('get', 'filter', this.value); return false;}"
								]
							]
						]
					]
				];
				if ($filter['id']){
					$content = json_decode($filter['content'], true);
					$additionalform = [
						[
							'type' => 'file',
							'hint' => LANG::GET('csvfilter.use_filter_input_file_hint', [':name' => $content['filesetting']['source']]),
							'attributes' => [
								'name' => LANG::GET('csvfilter.use_filter_input_file'),
								'required' => true,
								'accept' => '.csv'
							]
						], [
							'type' => 'br'
						], [
							'type' => 'numberinput',
							'attributes' => [
								'name' => LANG::GET('csvfilter.use_filter_month'),
								'value' => date('m'),
								'readonly' => true
							]
						], [
							'type' => 'numberinput',
							'attributes' => [
								'name' => LANG::GET('csvfilter.use_filter_year'),
								'value' => date('Y'),
								'readonly' => true
							]
						]
					];
					foreach($content['filter'] as $filtertype){
						if ($filtertype['apply'] === 'filter_by_comparison_file' && $filtertype['filesetting']['source'] !== 'SELF') array_push($additionalform, [
							'type' => 'file',
							'hint' => LANG::GET('csvfilter.use_filter_input_file_hint', [':name' => $filtertype['filesetting']['source']]),
							'attributes' => [
								'name' => LANG::GET('csvfilter.use_filter_compare_file') . '[]',
								'required' => true,
								'accept' => '.csv'
							]
						]);
					}
					array_push($return['body']['content'], $additionalform);
					$return['body']['form'] = [
						'data-usecase' => 'csvfilter',
						'action' => "javascript:api.csvfilter('post', 'filter', " . $filter['id'] . ")"
					];
				}

				$this->response($return);
				break;
		}
	}
}

$api = new CSVFILTER();
$api->processApi();

exit;
?>