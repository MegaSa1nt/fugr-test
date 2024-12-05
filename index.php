<?php
require_once __DIR__."/lib/library.php";
$method = $_SERVER['REQUEST_METHOD'];
$formData = Library::getRequestData();

$request = explode('/', $formData['request']);
/*
	Путь может возвращаться как "/api/v1/...", а не "api/v1/...", поэтому удаляем пустое значение
*/
if(empty($request[0])) array_shift($request);

if($request[0] == 'api') {
	/*
		Зачастую доступ к API затрудняет CORS
		Следующими заголовками мы выставляем возможность отправить реквестами с любых адресов, что, к примеру, полезно при работе с JavaScript
	*/
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST, DELETE');
	header("Access-Control-Allow-Headers: X-Requested-With"); 
	// Так как API всегда возвращает данные в JSON формате, то выставляем Content-Type, соответствующий формату JSON
	@header('Content-Type: application/json; charset=utf-8');
	try {
		/*
			Этой строкой мы вызываем роутер, отвечющий за нужный нам путь
			
			В данном примере, $request[1] = v1, а значит для создания второй версии нам всего лишь нужно создать папку v2
			$request[2] = notebook, тот API, который нам нужен
		*/
		require_once __DIR__."/routers/".$request[1]."/".$request[2].".php";
		array_splice($request, 0, 3); // Убрать api/v1/notebook из данных реквеста
		exit(Router::run($method, $request, $formData)); // Вернуть пользователю значение, которое дал роутер
	} catch(ErrorException $e) {
		/*
			Ловим исключение при какой-либо ошибке со стороны роутера
			Чаще всего это произойдёт, если пользователь введёт несуществующий роутер
		*/
		http_response_code(400);
		exit(json_encode([
			'message' => 'Неизвестная ошибка.'
		]));
	}
} else {
	/*
		Дальше запускается код, который не относится к API,
		то есть при необходимости можно добавить фронтенд
	*/
	exit(json_encode([
		'message' => 'Пока доступен только Rest API.'
	]));
}
?>