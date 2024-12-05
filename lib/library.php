<?php
class Library {
	/*
		Получаем данные с реквеста
		Ванильный PHP поддерживает только GET и POST, поэтому для остальных нам нужно получать данные вручную
	*/
	public static function getRequestData() {
		$formData = [];
		switch($_SERVER['REQUEST_METHOD']) {
			case 'GET':
			case 'POST':
				$formData = $_REQUEST;
				break;
			default:
				if(!empty(file_get_contents('php://input'))) {
					$inputData = explode('&', file_get_contents('php://input'));
					foreach($inputData AS &$data) {
						$dataDecoded = explode('=', $data);
						$formData[$dataDecoded[0]] = $dataDecoded[1];
					}
				}
				$formData['request'] = $_REQUEST['request'];
				break;
		}
		return $formData;
	}
	/*
		Избегаем плохих символов, которые могут испортить наш SQL код
		Из наиболее опасных он удаляет ", ', \
	*/
	public static function escape($string) {
		return mb_ereg_replace("[^A-Za-zА-Яа-я0-9\[\]\!\.\?\(\)\,\@\#\%\:\*\=\+\-\–\—ёЁ\/&_ ]", '', $string);
	}
}
?>