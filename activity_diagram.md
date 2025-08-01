```mermaid
stateDiagram-v2
    direction TB
    [*] --> ПросмотрКаталога
    ПросмотрКаталога --> ПоискФильтрация: Пользователь применяет\nфильтры/поиск
    ПоискФильтрация --> ОбновлениеКаталога: Система обновляет данные\n(без перезагрузки)
    ОбновлениеКаталога --> ПросмотрКаталога
    ПросмотрКаталога --> ДетальнаяСтраница: Выбор курса
    ДетальнаяСтраница --> ЗагрузкаДанных
    ЗагрузкаДанных --> ОтображениеДеталей
    ОтображениеДеталей --> ПереходКурс: "Перейти к курсу"
    ОтображениеДеталей --> ДобавитьВИзбранное: "В избранное"
    ПереходКурс --> [*]

    ДобавитьВИзбранное --> ПроверкаАвторизации{Авторизован?}
    ПроверкаАвторизации --> ФормаАвторизации: Нет
    ПроверкаАвторизации --> СохранениеИзбранного: Да

    ФормаАвторизации --> ВводДанных: Пользователь вводит данные
    ВводДанных --> Валидация{Данные верны?}
    Валидация --> ОшибкаАвторизации: Нет
    Валидация --> УспешнаяАвторизация: Да
    ОшибкаАвторизации --> ФормаАвторизации: Показать ошибку
    УспешнаяАвторизация --> СохранениеСессии: Сохранить статус\nв сессии
    СохранениеСессии --> СохранениеИзбранного

    СохранениеИзбранного --> ПодтверждениеДобавления: Курс добавлен
    ПодтверждениеДобавления --> ОтображениеДеталей
``` 