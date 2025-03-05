<?php

$fileName = 'ip_ru.bin';

$blockSize = 4096;

$testIpList = [
    '0.0.0.0',          // First (Not RU)
    '2.56.24.1',        // First RU
    '10.0.0.0',         // Private (RU)
    '127.0.0.0',        // Private (RU)
    '172.16.0.0',       // Private (RU)
    '192.168.0.0',      // Private (RU)
    '217.199.255.254',  // Last RU
    '255.255.255.255',  // Last (Not RU)
];

function ipToInt($ip)
{
    list($a, $b, $c, $d) = explode('.', $ip);

    $ipInt = ($a << 24) + ($b << 16) + ($c << 8) + $d;

    return $ipInt;
}

function isRuIp($ip, $fileName, $blockSize)
{
    $fd = fopen($fileName, 'rb');

    if ($fd === false) {
        echo 'File open error!' . PHP_EOL;

        exit(1);
    }

    $ipInt = ipToInt($ip);

    $bitsPerBlock = $blockSize * 8;

    $blockNumber = (int)floor((float)$ipInt / $bitsPerBlock);
    $blockOffset = $ipInt - $blockNumber * $bitsPerBlock;

    $byteNumber = (int)floor((float)$blockOffset / 8);
    $byteOffset = $blockOffset - $byteNumber * 8;

    echo $ip . ' ' . $ipInt . ' ' . $blockNumber . ' ' . $blockOffset . ' ' . $byteNumber . ' ' . $byteOffset . PHP_EOL;

    if (fseek($fd, $blockNumber * $blockSize) === -1) {
        fclose( $fd );

        echo 'File seek error!' . PHP_EOL;

        exit(2);
    }

    $blockData = fread($fd, $blockSize);

    if ($blockData === false) {
        fclose( $fd );

        echo 'File read error!' . PHP_EOL;

        exit(3);
    }

    fclose( $fd );

    $byte = unpack('C', $blockData[$byteNumber])[1];

    return ($byte & (1 << $byteOffset));
}

if ($argc > 1) {
    $ip = $argv[1];

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        echo 'IP not valid!' . PHP_EOL;

        exit(0);
    }

    $testIpList = [$ip];
}

foreach ($testIpList as $testIp) {
    $startTime = microtime(true);

    $present = isRuIp($testIp, $fileName, $blockSize);

    $stopTime = microtime(true);

    echo ($present ? 'RU' : 'Not RU') . PHP_EOL;

    echo round((($stopTime - $startTime) * 1000000), 0) . ' usec.' . PHP_EOL;

    echo PHP_EOL;
}
