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
		case 'getForm':
			$statement = $pdo->prepare("SELECT * FROM forms WHERE terms = :terms AND active=1 ORDER BY id DESC LIMIT 1");
			$statement->execute(['terms'=>$payload->content]);
			$row = $statement->fetch(PDO::FETCH_ASSOC);
			echo $row['content'];
		//else echo http_response_code(401);
		}
	//	else echo http_response_code(401);
}

?>