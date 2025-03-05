<?php

$url = 'https://stat.ripe.net/data/country-resource-list/data.json?resource=RU';

$fileName = 'ip_ru.bin';

$blockSize = 4096;

function ipToInt($ip)
{
    list($a, $b, $c, $d) = explode('.', $ip);

    $ipInt = ($a << 24) + ($b << 16) + ($c << 8) + $d;

    return $ipInt;
}

function ipAndMaskToRange($ip, $mask)
{
    $ipInt = ipToInt($ip);
    $maskInt = ($mask == 0) ? 0 : (~0 << (32 - $mask));

    $low = $ipInt & $maskInt;
    $high = $ipInt | (~$maskInt & 0xFFFFFFFF);

    return [$low, $high];
}

$curl = curl_init();

if ($curl === false) {
    echo 'CURL init error!' . PHP_EOL;

    exit(1);
}

curl_setopt_array(
    $curl,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FAILONERROR => false,
        CURLOPT_URL => $url,
    ]
);

$response = curl_exec($curl);

if ($response === false) {
    echo 'CURL exec error!' . PHP_EOL;

    curl_close($curl);

    exit(2);
}

$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if ($httpCode != 200) {
    echo 'HTTP error (' . $httpCode . ')!' . PHP_EOL;

    curl_close($curl);

    exit(3);
}

curl_close($curl);

$obj = json_decode($response);
$ipv4List = $obj->data->resources->ipv4;

$fd = fopen($fileName, 'wb');

if ($fd === false) {
    echo 'File create (open) error!' . PHP_EOL;

    exit(4);
}

$ipv4List = array_merge($ipv4List, [ // Add private networks
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',

    '127.0.0.0/8',
]);

sort($ipv4List, SORT_NATURAL);

$currentIpInt = 0;

$byteData = 0;
$byteOffset = 0; // [0..7]

$blockData = ''; // 4096
$blockOffset = 0; // [0..4095]

foreach ($ipv4List as $ipv4) {
    echo $ipv4 . PHP_EOL;

    if (strpos($ipv4, '/')) { // Net IP and net mask
        list($netIp, $netMask) = explode('/', $ipv4);

        $netRange = ipAndMaskToRange($netIp, $netMask);

        $netIpMinInt = $netRange[0];
        $netIpMaxInt = $netRange[1];
    } elseif (strpos($ipv4, '-')) { // Net min. and max. IP
        list($netIpMin, $netIpMax) = explode('-', $ipv4);

        $netIpMinInt = ipToInt($netIpMin);
        $netIpMaxInt = ipToInt($netIpMax);
    } else {
        echo '  IPv4 format error!' . PHP_EOL;

        continue;
    }

    if ($currentIpInt == $netIpMinInt) {
        echo '  ' . $netIpMinInt . ' ' . $netIpMaxInt . ' 1' . PHP_EOL;

        for ($i = $netIpMinInt; $i <= $netIpMaxInt; $i++) {
            $byteData |= 1 << $byteOffset;
            $byteOffset++;

            if ($byteOffset >= 8) {
                $blockData .= pack('C', $byteData);
                $blockOffset++;

                if ($blockOffset >= $blockSize) {
                    fwrite($fd, $blockData, $blockSize);

                    $blockData = '';
                    $blockOffset = 0;
                }

                $byteData = 0;
                $byteOffset = 0;
            }
        }
    } else {
        echo '  ' . $currentIpInt . ' ' . ($netIpMinInt - 1) . ' 0' . PHP_EOL;

        for ($i = $currentIpInt; $i <= ($netIpMinInt - 1); $i++) {
            $byteData &= ~(1 << $byteOffset);
            $byteOffset++;

            if ($byteOffset >= 8) {
                $blockData .= pack('C', $byteData);
                $blockOffset++;

                if ($blockOffset >= $blockSize) {
                    fwrite($fd, $blockData, $blockSize);

                    $blockData = '';
                    $blockOffset = 0;
                }

                $byteData = 0;
                $byteOffset = 0;
            }
        }

        echo '  ' . $netIpMinInt . ' ' . $netIpMaxInt . ' 1' . PHP_EOL;

        for ($i = $netIpMinInt; $i <= $netIpMaxInt; $i++) {
            $byteData |= 1 << $byteOffset;
            $byteOffset++;

            if ($byteOffset >= 8) {
                $blockData .= pack('C', $byteData);
                $blockOffset++;

                if ($blockOffset >= $blockSize) {
                    fwrite($fd, $blockData, $blockSize);

                    $blockData = '';
                    $blockOffset = 0;
                }

                $byteData = 0;
                $byteOffset = 0;
            }
        }
    }

    $currentIpInt = $netIpMaxInt + 1;
}

$lastIp = '255.255.255.255';
$lastIpInt = ipToInt($lastIp);

echo '  ' . $currentIpInt . ' ' . $lastIpInt . ' 0' . PHP_EOL;

for ($i = $currentIpInt; $i <= $lastIpInt; $i++) {
    $byteData &= ~(1 << $byteOffset);
    $byteOffset++;

    if ($byteOffset >= 8) {
        $blockData .= pack('C', $byteData);
        $blockOffset++;

        if ($blockOffset >= $blockSize) {
            fwrite($fd, $blockData, $blockSize);

            $blockData = '';
            $blockOffset = 0;
        }

        $byteData = 0;
        $byteOffset = 0;
    }
}

fclose($fd);
