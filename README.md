# Определение принадлежности IP к РФ

## load_data.php

1. Скрипт загружает список IPv4 относящихся к РФ по API из [RIPE](https://stat.ripe.net/data/country-resource-list/data.json?resource=RU).
2. Дополняет список IP частных сетей (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 и 127.0.0.0/8).
3. Преобразует во внутренний бинарный формат (см. описание ниже).
4. Сохраняет в файле ip_ru.bin (ровно 512 Мб.)

## test_ip.php

Проверяет указанный в параметре IP на принадлежность к РФ или к частной сети (см. описание алгоритма проверки ниже).
Если IP не указан, выводит проверку для нескольких тестовых IP указанных в скрипте.
В выводе присутствует отладочная информация: тестируемый IP, его целочисленное представление, номер блока, смещение в блоке, номер байта, смещение в байте (бит), а также время проверки в микросекундах.

## Формат файла

Для IPv4 всего может быть 2^32 IP адресов или 4294967296.
Так как нам требуется ответить на вопрос: "относится тестовый адрес к РФ (частной сети) или нет?", нам достаточно хранить 0 или 1 для каждого возможного IP (0 - не относится, 1 - относится).
Т.е. достаточно одного бита на каждый IP адрес.
Все адреса, не относящиеся к РФ (частной сети), заполняем 0.
Все адреса, относящиеся к РФ (частной сети), заполняем 1.
Упаковываем в байты (беззнаковый char) и сохраняем на диске.
Размер файла составит ровно 512 Мб (4294967296 адресов / 8 бит).
Для проверки достаточно будет проверить нужный бит, который расположен в файле со смещением равным IP (предварительно преобразованным к безнаковому int).
Также мы хотим, чтобы операция проверки выполнялась за минимальное время для HDD.
Для этого, чтение с диска должно осуществляться блоком с размером, как правило, 4096 байт.
Для SSD можно читать сразу только нужный байт.

## Алгоритм проверки

1. Открываем файл ip_ru.bin

```php
$fileName = 'ip_ru.bin';

$fd = fopen($fileName, 'rb');
```

2. Преобразуем тестируемый IP в целое беззнаковое число

```php
$ipInt = ipToInt($ip);
```

3. Определяем номер блока, который содержит нужный нам IP (который нужно прочитать с диска)

```php
$blockSize = 4096;

$bitsPerBlock = $blockSize * 8;

$blockNumber = (int)floor((float)$ipInt / $bitsPerBlock);
```

4. Смещаемся в файле к началу нужного блока

```php
fseek($fd, $blockNumber * $blockSize)
```

5. Читаем один блок с диска

```php
$blockData = fread($fd, $blockSize);
```

6. Определяем номер байта в блоке, который содержит нужный нам IP

```php
$blockOffset = $ipInt - $blockNumber * $bitsPerBlock;

$byteNumber = (int)floor((float)$blockOffset / 8);
```

7. Получаем значение нужного байта и распаковываем его из формата хранения на диске (беззнаковый char)

```php
$byte = unpack('C', $blockData[$byteNumber])[1];
```

8. Определяем номер бита в байте, который содержит нужный нам IP

```php
$byteOffset = $blockOffset - $byteNumber * 8;
```

9. Производим проверку нужного бита в байте

```php
return ($byte & (1 << $byteOffset));
```
