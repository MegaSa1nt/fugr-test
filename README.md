# fugr-test
Тестовое задание

В каждом файле есть комментарии о том, как код работает
Rest API придерживается формату, указанному в тестовом задании

Swagger: https://fugr.gcs.icu/swagger/

Тестировал вручную при помощи ввода различных данных в поля ввода

Главные файлы:
- index.php: через него и проходит весь траффик по API
- lib/library.php: библиотека с полезными функциями
- routers/v1/notebook: роутер для взаимодействия с записной книжкой

Добавить новый путь API:
1. Создать в папке `routers/v1/` файл `РОУТЕР.php`
2. Внутри него создать класс `Router`, в котором будет публичная статичная функция `run` с переменными:
  - $method — Метод реквеста
  - $request — Реквест, разбитый на массив через разделитель `/`, перед началом нужно убрать первые 3 значения (api/v1/router)
  - $formData — данные, переданные с реквестом