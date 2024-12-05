<?php
class Router {
	/*
		Этот метод будет одинаковым во всех файлах, так как он выполняет функции по методу реквеста
		Очень прост в добавлении нового метода, так как просто нужно будет добавить ещё один case к switch
	*/
	public static function run($method, $request, $formData) {
		switch($method) {
			case 'GET':
				return self::_get($request, $formData);
				break;
			case 'POST':
				return self::_post($request, $formData);
				break;
			case 'DELETE':
				return self::_delete($request, $formData);
				break;
		}
		http_response_code(400);
		return json_encode(['message' => 'Метод не поддерживается.']);
	}
	/*
		Эта функция вызывается при методе реквеста GET
		
		Если пользователь не укажет ID записи, то ему вернётся 10 последних записей с возможностью постраничного просмотра
		Для смены страницы нужно отправить переменную page с номером страницы
		Пример: https://fugr.gcs.icu/api/v1/notebook/?page=2 вернёт вторую страницу записей
		
		Для просмотра конкретной записи пользователю нужно указать ID записи
		Пример: https://fugr.gcs.icu/api/v1/notebook/13 вернёт запись с ID 13
		
		Возвращает запис(ь, и)
	*/
	private static function _get($request, $formData) {
		if(!empty($request[0])) { // Если пользователь предоставил ID записи, например /api/v1/notebook/1
		
			if(!is_numeric($request[0])) {
				http_response_code(400);
				return json_encode(['message' => 'Неверные данные.']);
			}
			
			$noteID = (int)$request[0];
			$note = self::getNoteByID($noteID);
			
			if(empty($note)) { // Функцией empty я проверяю, существует ли запись или нет 
				http_response_code(404);
				return json_encode(['message' => 'Запись не найдена.']);
			}
			
			return json_encode(self::parseNote($note));
		} else { // Постраничный обзор записей
			$page = (int)$formData['page'] ?: 1;
			$notes = self::getAllNotes($page);
			
			return json_encode([
				'page' => [
					'current' => $page,
					'total' => $notes['pages']
				],
				'notes' => $notes['notes']
			]);
		}
	}
	/*
		Эта функция вызывается при методе реквеста POST
		
		Если пользователь указал ID записи, то эта функция обновит запись теми данными, что укажет пользователь
		Данные для обновления:
		{
			full_name: ФИО,
			company: Компания,
			phone: Телефон,
			email: Почта,
			birthday: Дата рождения человека. Для отправки должен использоваться <input type="datetime-local">
			photo: Файл с новой фотографией, максимум 50 МБ
		}
		
		Для добавления новой записи, в реквесте не должно быть ID записи
		Данные для записи:
		{
			full_name: ФИО, *обязателен*
			company: Компания,
			phone: Телефон, *обязателен*
			email: Почта, *обязательна*
			birthday: Дата рождения человека. Для отправки должен использоваться <input type="datetime-local">
			photo: Файл с новой фотографией, максимум 50 МБ
		}
		
		Возвращает ID записи
	*/
	private static function _post($request, $formData) {
		require __DIR__."/../../lib/connection.php";
		require_once __DIR__."/../../lib/library.php";
		if(!empty($request[0])) {
			$noteID = (int)$request[0];
			$fullName = Library::escape($formData['full_name']) ?: null;
			$company = Library::escape($formData['company']) ?: null;
			$phone = Library::escape($formData['phone']) ?: null;
			$email = Library::escape($formData['email']) ?: null;
			if(isset($formData['birthday'])) {
				try {
					// Если пользователь ввёл плохие данные, то PHP поймает ошибку и не изменит дату
					$birthday = (new DateTime($formData['birthday']))->format('Y-m-d');
				} catch(Exception $e) {
					$birthday = null;
				}
			}
			$photo = ($_FILES && $_FILES['photo']['error'] == UPLOAD_ERR_OK) ? $_FILES['photo'] : null;
			
			$dataToUpdateArray = [];
			// Использовать данные напрямую в данном случае безопасно, потому что мы отсекаем плохие символы через Library::escape
			if($fullName !== null) $dataToUpdateArray[] = 'full_name = "'.$fullName.'"';
			if($company !== null) $dataToUpdateArray[] = 'company = "'.$company.'"'; 
			if($phone !== null) $dataToUpdateArray[] = 'phone = "'.$phone.'"'; 
			if($email !== null) $dataToUpdateArray[] = 'email = "'.$email.'"'; 
			if($birthday !== null) $dataToUpdateArray[] = 'birthday = "'.$birthday.'"'; 
			if($photo !== null) {
				if($photo['size'] >= 50 * 1024 * 1024) {
					http_response_code(400);
					return json_encode(['message' => 'Фотография весит более 50 МБ.']);
				}
				$info = new finfo(FILEINFO_MIME_TYPE); // Использовать $photo['type'] небезопасно, так как оно отталкивается от расширения в названии, которое легко меняется
				$fileType = $info->buffer(file_get_contents($photo['tmp_name']));
				if(substr($fileType, 0, 6) != 'image/') {
					http_response_code(400);
					return json_encode(['message' => 'В качестве фотографии нужно загружать фотографию.']);
				}
				move_uploaded_file($photo['tmp_name'], __DIR__ ."/../../photos/".$noteID.".jpg");
			}
			if(empty($dataToUpdateArray) && $photo === null) {
				http_response_code(400);
				return json_encode(['message' => 'Вы ничего не изменили.']);
			}
			
			if(!empty($dataToUpdateArray)) { // Пользователь всё ещё может изменить только фотографию
				$sqlQuery = "UPDATE notes SET ".(implode(', ', $dataToUpdateArray)).' WHERE noteID = :noteID';
				$updateNote = $db->prepare($sqlQuery);
				$updateNote->execute([':noteID' => $noteID]);
			}
			
			return json_encode(['noteID' => $noteID]);
		} else {
			$fullName = Library::escape($formData['full_name']) ?: '';
			$company = Library::escape($formData['company']) ?: '';
			$phone = Library::escape($formData['phone']) ?: '';
			$email = Library::escape($formData['email']) ?: '';
			if(isset($formData['birthday'])) {
				try {
					// Если пользователь ввёл плохие данные, то PHP поймает ошибку и использует стандартную дату
					$birthday = (new DateTime($formData['birthday']))->format('Y-m-d');
				} catch(Exception $e) {
					$birthday = '1970-01-01';
				}
			}
			$photo = ($_FILES && $_FILES['photo']['error'] == UPLOAD_ERR_OK) ? $_FILES['photo'] : null;

			if(empty($fullName) || empty($phone) || empty($email)) {
				http_response_code(400);
				return json_encode(['message' => 'Неверные данные.']);
			}

			if($photo !== null) {
				if($photo['size'] >= 50 * 1024 * 1024) {
					http_response_code(400);
					return json_encode(['message' => 'Фотография весит более 50 МБ.']);
				}
				$info = new finfo(FILEINFO_MIME_TYPE); // Использовать $photo['type'] небезопасно, так как оно отталкивается от расширения в названии, которое легко меняется
				$fileType = $info->buffer(file_get_contents($photo['tmp_name']));
				if(substr($fileType, 0, 6) != 'image/') {
					http_response_code(400);
					return json_encode(['message' => 'В качестве фотографии нужно загружать фотографию.']);
				}
			}

			$uploadNewNote = $db->prepare('INSERT INTO notes (full_name, company, phone, email, birthday, timestamp) VALUES (:full_name, :company, :phone, :email, :birthday, :timestamp)');
			$uploadNewNote->execute([':full_name' => $fullName, ':company' => $company, ':phone' => $phone, ':email' => $email, ':birthday' => $birthday, ':timestamp' => time()]);
			$noteID = $db->lastInsertId();
			
			if($photo !== null) move_uploaded_file($photo['tmp_name'], __DIR__ ."/../../photos/".$noteID.".jpg");
			
			return json_encode(['noteID' => $noteID]);
		}
	}
	/*
		Эта функция вызывается при методе реквеста DELETE
		
		Она удаляет запись с ID, который укажет пользователь
		Вернёт {success: true} при успехе или ошибку
	*/
	private static function _delete($request, $formData) {
		require __DIR__."/../../lib/connection.php";
		if(!is_numeric($request[0])) {
			http_response_code(400);
			return json_encode(['message' => 'Неверные данные.']);
		}
		
		$noteID = (int)$request[0];
		$note = self::getNoteByID($noteID);
		
		if(empty($note)) {
			http_response_code(404);
			return json_encode(['message' => 'Запись не найдена.']);
		}
		
		$deleteNote = $db->prepare("DELETE FROM notes WHERE noteID = :noteID");
		$deleteNote->execute([':noteID' => $noteID]);
		if(file_exists(__DIR__ ."/../../photos/".$noteID.".jpg")) unlink(__DIR__ ."/../../photos/".$noteID.".jpg");
		
		return json_encode(['success' => true]);
	}
	/*
		Эта функция избавляет запись от дублирования данных в ней и добавляет ссылку на фотографию
	*/
	private static function parseNote($note) {
		return [ // Если вернуть $note напрямую, то данные вернутся дважды, поэтому нужно вручную прописать каждую переменную
			'noteID' => $note['noteID'],
			'full_name' => $note['full_name'],
			'company' => $note['company'],
			'phone' => $note['phone'],
			'email' => $note['email'],
			'birthday' => $note['birthday'],
			'photo_url' => file_exists(__DIR__ ."/../../photos/".$note['noteID'].".jpg") ? "https://".$_SERVER['HTTP_HOST'].'/photos/'.$note['noteID'].'.jpg' : null,
			'timestamp' => $note['timestamp']
		];
	}
	/*
		Эта функция возвращает запись с указанным вами ID или ничего, если не найдено
	*/
	private static function getNoteByID($noteID) {
		require __DIR__."/../../lib/connection.php";
		$note = $db->prepare("SELECT * FROM notes WHERE noteID = :noteID");
		$note->execute([':noteID' => $noteID]);
		return $note->fetch();
	}
	/*
		Эта функция возвращает все записи на текущей странице (максимум 10 штук на странице)
	*/
	private static function getAllNotes($page) {
		require __DIR__."/../../lib/connection.php";
		$pageOffset = ($page - 1) * 10;
		$notes = $db->prepare("SELECT * FROM notes ORDER BY noteID DESC LIMIT 10 OFFSET $pageOffset"); // Если указать оффсет через execute(':pageOffset' => $pageOffset), то MySQL вернёт синтаксическую ошибку
		$notes->execute();
		$allNotes = $notes->fetchAll();
		$notesCount = self::getNotesCount();
		
		$notes = [];
		foreach($allNotes AS &$note) {
			$notes[] = self::parseNote($note);
		}
		
		return [
			'pages' => ceil($notesCount / 10),
			'notes' => $notes
		];
	}
	/*
		Эта функция возвращает количество всех записей
	*/
	private static function getNotesCount() {
		require __DIR__."/../../lib/connection.php";
		$notes = $db->prepare("SELECT count(*) FROM notes");
		$notes->execute();
		return $notes->fetchColumn();
	}
}
?>