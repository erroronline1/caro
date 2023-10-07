<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST'){
	switch ($payload->request){
		case 'newsletter':
		break;
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'PUT'){
	switch ($payload->request){
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE'){
	switch ($payload->request){
	}
	//	else echo http_response_code(401);
}

elseif ($_SERVER['REQUEST_METHOD'] == 'GET'){
	switch ($payload->request){
		case 'getForms':
			// retrieve latest active entries according to requested names
			$requestedNames = explode(',',dbSanitize($payload->content));
			$statement = $pdo->prepare("SELECT * FROM forms WHERE id IN (SELECT MAX(id) FROM forms WHERE name IN ('". implode("','", $requestedNames)."') AND active=1 GROUP BY name)");
			$statement->execute();
			$result = $statement->fetchAll(PDO::FETCH_ASSOC);
			// order by $payload->content sequence with anonymous function passing $payload into scope
			usort($result, function ($a, $b) use ($requestedNames){
				if (array_search($a['name'], $requestedNames) <= array_search($b['name'], $requestedNames)) return -1;
				return 1;
				});
			// rebuild result array
			$form = false;
			$content = [];
			foreach($result as $key => $val) {
				$currentcontent=json_decode($val['content'], true);
				if (array_key_exists('form', $currentcontent)) $form = array_merge(gettype($form)==='boolean'? []: $form, $currentcontent['form']);
				array_push($content, ...$currentcontent['content']);
			}
			// reassign $result
			$result=[];
			if ($form!==false) $result['form'] = $form;
			$result['content'] = $content;
			echo json_encode($result);
			break;
		default:
			echo http_response_code(400);
		}
	//	else echo http_response_code(401);
}

?>