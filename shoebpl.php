<?php
/**
 * JTV Aggregator — single-file PHP script (Full Power)
 * Open: https://yourdomain.com/jtv.php
 */

// ---------- CORS preflight ----------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    exit;
}

// Allow CLI execution (for GitHub Actions), but block non-GET web requests
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    corsJson(['error' => 'Method not allowed'], 405);
}

// ---------- Config ----------
const UPSTREAM_URL      = 'https://falling-mouse-de67.bmera5952.workers.dev/';
const CLOUDPLAY_URL     = 'https://m3u.cloudplay.qzz.io/pllivejtv2.json';
const STAR_CHANNELS_URL = 'https://shoebinfo.qzz.io/jtv/mypllive.json';
const MBLIVE_COOKIE_URL = 'https://odd-wave-74d0.shoeb66445.workers.dev/';
const M3U_FALLBACK_URL  = ''; // disabled

const USER_AGENT_OUT    = 'Premium Plugx';
const USER_AGENT_FETCH  = 'Cloudflare-Worker/2.0';

// =====================================================================
//  Star Sports — 5-key comma-separated ClearKey map
//  (kid:key,kid:key,kid:key,kid:key,kid:key)
//  Keyed by the Jio channel id used in the fallback array below.
// =====================================================================
$STAR_SPORTS_KEYS = [

    // Star Sports 2 Hindi HD (id 1984)
    '1984' => '5b50f11a09a15275b5742ef2d6e001e0:9b9ce35c8d91340939031aba5e433991'
            . ',22e4de22a39b5ce78425c739c4941e7b:b4134f4a41cc0d214ba909cc4ebdb0ba'
            . ',17a32401741c5072bd2f550f72be7a42:0ddcf0f656f52008a2276a85adbcf42e'
            . ',0c1a4c4f4a60538a8841ad67b5df3488:939f425e0ef782f16cc9db4b84b47195'
            . ',8e48435ba038548299ee73fef998535d:b89d18e54ad2b5576c27c3259db19a3e',

    // Star Sports Khel (id 1998)
    '1998' => '906cc35528ca58348e7fed58f8796687:3a1eaff9da6a0083567fb1ea1eaa21f5'
            . ',1dfe1c29b8f15ab3a158bb27eb5b8d3f:432ff00824d61ff1da1348cc53e0968c'
            . ',942646c32cde5325a7c33b137cf7d46e:ebb1edf4d1ca69738d8cf378a00ad35d'
            . ',2898824f2440509698d04f0109f1d285:58e2fbc4e5ae22f973c21c402340debc'
            . ',1eb81f72a86d56c3bdfe905c2e987d1d:4720e2ac970828d2befb2cf838d486fb',

    // Star Sports 1 HD (id 1106)
    '1106' => '45825561f4ef516691a9e16d1d35dd1d:3951fe7fbf4b0b33e6246fc401f76797'
            . ',bf95222d9be85d85a91281e796e99481:bc932276031b3e63758cf67b147c3557'
            . ',f217e9fe133e5b948c323abef1e39b47:63640dc4430d14003def0c97a2dbab4d'
            . ',eda3132244745c10920b01536bc8a75b:2b5f8bdba279032943a040078bb22b35'
            . ',06dd6287c4b956419d15d6c8e513aeed:a82d6b850b5c281c0054fc6d55e1c6ba',

    // Star Sports 1 Hindi HD (id 1108)
    '1108' => 'a1ce6b9514f35174bda80ddd425bfd88:5c5e158724eced92f92ca8286fb28fd9'
            . ',dd2fa604afc45e528f8a2283bf3cb628:bf01e6e8ff74797fb5215fead779e8b9'
            . ',209975f6753d5de1ac26d604dace5313:1c54093eff75aeeccd30ebbc53c0e5a3'
            . ',7bdccdb3dd015c37a0c3bd70675c8e4c:6f1b59376090d1f7ce33cbd264e87c36'
            . ',11073b8f426e5092a2effada3bfaf721:5d1c59a8052956bd056810f260161e5f',

    // Star Sports 2 HD (id 1109)
    '1109' => 'b99144f27b865d6ba1007fead82b2783:b2f10f09a771327b9bc69484d78e4595'
            . ',04204c98718a52bf9e88d5e676fd7a63:7eebb539b03f674a26e84ae096320c70'
            . ',33fab6f82a59590eb875f949389e03a9:26eff9e972154e624faf259ae0ee78ec'
            . ',a5ad7dec861451a49029044a3b9d30bb:eff028a1ed3253b6a68b6a9cb4f1a0e4'
            . ',c1ea31b9099b5030b14b67b41dc547d2:db74fb55442bc9cf7e8e9f364b3fe37f',

    // Star Sports Select 2 (id 1114)
    '1114' => '2b307daa57a75698acfac3e85d4ff8c5:d3c17909d061caa2e0a9c75e195ae3d4'
            . ',22531ef1b5805a5cb5afc03f33468fac:3dfc474db4086f173d342f20cdc8e04c'
            . ',0fde10db386c58aaa92f5932092f0039:db555aaa769ad60c24dc1ef9005a056b'
            . ',2af0f67137b15f5aabcaa3f2661ba59d:f3a33dbc38942246a89ff471d0062c66'
            . ',c18272dca01056fb83d6ef0cfd0a93ad:52b8bf00ebe06d6e7cdcd221faed846a',

    // Star Sports 1 Hindi (id 362)
    '362'  => '30039b6e2c545c01aa84e649875c4977:7aa610c68000fe9ed97851d40a52abff'
            . ',c2f0a204c6c353428954740d05d73c3d:6543b1984330109886d2864d06096590'
            . ',a9cb8b39035a5393a4450f56c35b15a2:96f3585aab24109d64b74e9f0c6a5ed3'
            . ',6a28a05d0f9a5e09987e4267eab5a947:38d9de645400fe2df985177aafb6a248'
            . ',187e53dccc7c50f5b43306635bc93adb:72f9a4a4ff898de730991ae465d06388',

    // Star Sports Select 1 HD (id 460)
    '460'  => '8fc8499852ea5182b2a9307bd921cea1:c0ff2fbd59e26d6633f98af470c4d7b6'
            . ',272c9b98274250f1b0bae9e19612275a:0fbbb12efcf16e2817b3c881847b2abf'
            . ',fe38155e1cee53778db627b35bd2d20b:13d7c44c0e3fee8d9186a0491207629c'
            . ',337fe34bbf2152c1bfe5ea8787072cd4:4efacd3a5af887dea393f0b957d266b6'
            . ',cc04426ac28359a7b620934ddbbecb7c:0074c460557b1f21a60fa0aada550e3a',

    // Star Sports Select 2 HD (id 461)
    '461'  => '2c443dffefb256a5b7a1364cc4af3529:1d78f35200eb5977a95de669788cf128'
            . ',cb4b21e0cad15e00ba3819fb91660fec:fe6480d2894746ba5799a3c2847e5589'
            . ',e5c47cf992a75f98b4e1a4d262de61e8:441f225881f816c6bedece0e86ef80e7'
            . ',928d9058c0a8550fb6ba315124a76cd5:46811be73195049a23b731bd9344cad4'
            . ',25fbb2a932b25e9aacae947b2f873d3c:ac6a6a416b9d26be6819e80c2fe5d39f',

    // Star Sports 3 (id 1389)
    '1389' => '2904732c8fb55ebeaa0f59316916997e:f69101b71872dcfb2cd1987d3773c0f4'
            . ',2f040ce640ee57978b0bcbb975af00a4:88c56371262c1bbe843c174720bbdeb0'
            . ',6fbf6696ff3051768337a34da0e66eb8:cae19d171fb1015a89342e5185f8cc7b'
            . ',c19b6cfea03150bea01a255345e5d706:8ed757eda2b740285bcf9a8fd9263422'
            . ',82dc7db814ef5165a3d93102f40a5142:f767c4c59fbfeaf84754994a0ed5dc8b',

    // Star Sports 1 Kannada (id 1650)
    '1650' => 'bab01ce8ab695712a5fe390f1b4ffca2:5685130a845e217080b84fc170b67f00'
            . ',5934f65f151c581d9dc75e181c7a406e:ffd92e68261477c61df082b64e648cbf'
            . ',ec959436fe455d3a8af330c4c93e86cc:56f3b61ef52fb050671036bf50a109dd'
            . ',fb2bb07aecbf5bc2b807b2dced129cda:80d4747512a52bb7c64c8f60813a0f8f'
            . ',ae3f86f454395a8ab0793c8a619aa1f6:6034309b589445745ec1c00fd6161a4a',

    // Star Sports 1 Telugu (id 1651)
    '1651' => '8aa7da0842d65285b23e55619ab0f1c6:fe01e21ac81a19049d3ba82b5bd33dfa'
            . ',c60582b4c0b95e80ae34b1a462f07990:9967dfba4b60a86a9c7a4c861d26337c'
            . ',4705b7d90af65fa28d3e6b7c9e6b7fc2:bfdbe4ecb7f1496978eb9d022a6640b5'
            . ',e3321b5b1af253f39010ce4bf6085da6:8eb2ab45a84ca9eef4630d6a2ce9f786'
            . ',50eaa3fe2ce95eb4908495ea19e3b6d5:a4a789b10f04c39070d64ef131b59911',

    // Star Sports 2 Tamil HD (id 3273)
    '3273' => 'bd9dfd385dbc56a99a048f88d3828571:eecf613dad0b8250ffaddc7ba86be68b'
            . ',75ed4a2f9c96531fbdc16458ec75e24d:d61e00cf48a772526e85dccbdfc166ae'
            . ',6c41267ed8725915bee0695dd5e76d40:4e2ba836b17c916e7368b30453987f2f'
            . ',a2e868d50d315372a0e7375ee90375d1:0d885213a727867407ca23037e6ffe53'
            . ',92cfd893ab8551d59a773afce91fc790:906e0d6c4accf6bdaad9a6167f8ae53a',

    // Star Sports 2 Telugu HD (id 3274)
    '3274' => '5515e7889d225404939f53765feba2ea:56fa91e2ee09e5a989c630cb3b2df13d'
            . ',22da87a3906750e78c0e770cc4b88c7e:d25db631bbcdb027d9a3fdddf5ddeba8'
            . ',86180ee8e6b05175adddc5eb66b01faf:eaaf6aec5ea8cedeb6d248f2e96d6b93'
            . ',7635f19465ac599197cdf889d9ad9739:068980c25dd4fe33631fe07a35c1289f'
            . ',01fc3874f21058dcbf8204f07616ce81:8ba560fb1fcdfeaddfc254965e3a42f6',

    // Star Sports 1 Tamil HD (id 3277)
    '3277' => '1e9099af7f025ad6b3fd363bb2db8faf:c45a762695c40f6675b8b56ac50e97a5'
            . ',8d89858c927e55a2b03faaa98fca9eb2:f74858f329157adebdbc5556d0417f47'
            . ',1e6246cad111577f90bd2082378b7ed2:976e4e4f92d10b8d1702559a9babb5e0'
            . ',2487a38f900d5fe1ba98eed635d96c6e:58e2099b555b7ea59dee0b957b694cea'
            . ',439d31fa05525a99b856e852d035023b:83728b98188b1fa8bf69509e1796fb27',

    // Star Sports 1 Telugu HD (id 3278)
    '3278' => 'a56a509011675303900ead16165ba38f:c4c4c7aec0ff4a3fa0c6d8cb1ae56c30'
            . ',45bfaa5f2e8c5723a8da41a5c9cd38c1:672fa31cd32888671db2daf259479d49'
            . ',2130ba84a11d58cb8cf1fae897c241cf:f812838e18377db5fe72277934548d83'
            . ',c7ecbd81d476502b9f25e08bfa07b842:52e369879fd23e08a6158c8c1224c4dd'
            . ',a2bcadea362c563d9a9f68dfb2557087:5f4370e02e5eea8c9771d36a27a14afb',

    // Star Sports 2 Hindi (id 1985)
    '1985' => 'f9af22f6d76f5f5d98a35eed7fc35292:b979fbf840b5c984733d28aaf683762e'
            . ',d20fa4f491ff5e74a4b5b149caed397b:913de95cba91eadbeee9784882185f70'
            . ',f52d8e6b43c95ee7b5d1092c127049bc:8b6f00199715e5e736fc970e2e887709'
            . ',b4ae2599a33f5cbc8eff216f82284cef:98cc66f56da9a4e8163f5f831f03cd43'
            . ',602ce22ddc995d4495d10c1adeeb8e8c:be784f89f9513333c7621900c7a1d89b',

    // Star Sports 2 Telugu (id 2852)
    '2852' => 'e85ffc940cfc5c5790a24b714acef1cd:fda416daac7608bfd608ab4c5b724a84'
            . ',5387c6a9f68a5cecbe1238194db9a82a:14345a3dab1d1bf5e64570b5aa377883'
            . ',a83960ba4fc854e88c3bcd7f16f08f27:56218453c4619b01c1a0913779234faf'
            . ',a64b68eea65c5881a0fc5742bd389806:9b446fde7106eb1b7821c5cf251df932'
            . ',4d3a6feedae059fb8f92f5bcba25128d:46c14f188789753b7befc2707829cb6a',

    // Star Sports 2 Tamil (id 2853)
    '2853' => '0a0ad7fdf02955d483496e5d5ad41b64:cc1d01a4ccfd47da4f5ff62e20686505'
            . ',dc4c605a752d535e8c0e31b9a5deae16:21cb8bd7a0606e2dd9fb190ff719f126'
            . ',a9f12248bf2a5da99366dd6ce07ff0e5:e87623a2552185d5fbe2f227354c2b15'
            . ',bfb4dda563535486a380c44fecc92015:27bbe04fb5447de6f3286e1030c7b179'
            . ',b9fae8a03b2750a4b0db12f0f6b65f15:3b1ab225fb8e08c9b59cfd6ededaddc8',
];

// ---------- Fan-out fetch ----------
$sources = [
    'upstream'  => UPSTREAM_URL,
    'cloudplay' => CLOUDPLAY_URL,
    'star'      => STAR_CHANNELS_URL,
    'mblive'    => MBLIVE_COOKIE_URL,
];
if (M3U_FALLBACK_URL !== '') {
    $sources['m3u'] = M3U_FALLBACK_URL;
}

$responses    = fetchAll($sources, 20);
$upstreamRes  = $responses['upstream']  ?? ['ok' => false];
$cloudplayRes = $responses['cloudplay'] ?? ['ok' => false];
$starRes      = $responses['star']      ?? ['ok' => false];
$mbliveRes    = $responses['mblive']    ?? ['ok' => false];
$m3uRes       = $responses['m3u']       ?? ['ok' => false];

// ---------- Upstream channels ----------
$channels = [];
if ($upstreamRes['ok']) {
    $json = json_decode($upstreamRes['body'], true);
    if (is_array($json)) {
        $channels = $json;
    } else {
        error_log('Upstream response is not an array');
    }
} else {
    error_log('Upstream fetch failed: ' . ($upstreamRes['error'] ?? 'unknown'));
}

// ---------- CloudPlay cookie map ----------
$cloudplayCookieMap = [];
if ($cloudplayRes['ok']) {
    $cpData = json_decode($cloudplayRes['body'], true);
    if (is_array($cpData)) {
        foreach ($cpData as $item) {
            if (!empty($item['id']) && !empty($item['headers']['cookie'])) {
                $cloudplayCookieMap[(string)$item['id']] = $item['headers']['cookie'];
            }
        }
    }
}

// ---------- Star channels cookie map ----------
$starCookieMap = [];
if ($starRes['ok']) {
    $starData = json_decode($starRes['body'], true);
    if (is_array($starData)) {
        foreach ($starData as $item) {
            if (empty($item['id']) || empty($item['url'])) continue;
            $cookie = extractHdneaCookie($item['url']);
            if ($cookie) $starCookieMap[(string)$item['id']] = $cookie;
        }
    }
}

// ---------- M3U emergency fallback cookie map ----------
$m3uCookieMap = [];
if ($m3uRes['ok']) {
    $lines     = explode("\n", $m3uRes['body']);
    $currentId = null;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '#EXTINF:') === 0) {
            $currentId = preg_match('/tvg-id="([^"]*)"/', $trimmed, $m) ? $m[1] : null;
        } elseif (strpos($trimmed, '#EXTHTTP:') === 0 && $currentId) {
            $headerData = json_decode(substr($trimmed, strlen('#EXTHTTP:')), true);
            if (is_array($headerData) && !empty($headerData['cookie'])) {
                $m3uCookieMap[(string)$currentId] = $headerData['cookie'];
            }
            $currentId = null;
        } elseif (strpos($trimmed, 'https://') === 0) {
            $currentId = null;
        }
    }
}

// ---------- Shared mblive cookie (EXCLUSIVE for jiotvmblive) ----------
$mbliveCookie = null;
if ($mbliveRes['ok']) {
    $mbliveData = json_decode($mbliveRes['body'], true);
    if (is_array($mbliveData) && count($mbliveData) > 1 && !empty($mbliveData[1]['cookie'])) {
        $mbliveCookie = $mbliveData[1]['cookie'];
    }
}

// ---------- Transform upstream channels ----------
$transformed = [];
foreach ($channels as $ch) {
    if (!is_array($ch)) continue;
    $transformed[] = transformChannel($ch, $cloudplayCookieMap, $starCookieMap, $m3uCookieMap, $mbliveCookie, USER_AGENT_OUT);
}

// ---------- Hardcoded pllive fallback channels ----------
$plliveChannelsFallback = [
    ['name'=>'Zee Bangla',              'id'=>'625',    'category'=>'Entertainment',  'keyId'=>'811e683089455a1fa9c7e0d5d2bdb5fd','key'=>'2782858ad6e8d5a7964ddcd7c2cdb207','logo'=>'https://img.media.jio.com/tvpimages/10/93/300323_1768297625116_l_medium.jpg',  'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/ZeeBangla_BTS/WDVLive/index.mpd'],
    ['name'=>'Zee Bangla HD',           'id'=>'1977',   'category'=>'Entertainment',  'keyId'=>'91159e05d6c45bcc9586a6358fa2fbf9','key'=>'23445ad11eb79efae433df70608e3b87','logo'=>'https://img.media.jio.com/tvpimages/71/9/304105_1768467749246_l_medium.jpg',  'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/ZeeBanglaHD_BTS/WDVLive/index.mpd'],

    ['name'=>'Star Sports Select 1 HD', 'id'=>'460',    'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['460'],  'logo'=>'https://img.media.jio.com/tvpimages/96/65/302113_1749664252703_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_Select_HD_1_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports Select 1',    'id'=>'1123',   'category'=>'Sports',         'keyId'=>'ea7985dacbb95f1c9d62e5a1675142e5','key'=>'39d5910ca04841b5f32bf24623cdae58','logo'=>'https://img.media.jio.com/tvpimages/13/82/301994_1749664069587_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_Select_1_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports Select 2 HD', 'id'=>'461',    'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['461'],  'logo'=>'https://img.media.jio.com/tvpimages/98/79/302114_1749665414817_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_Select_HD_2_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports Select 2',    'id'=>'1114',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1114'], 'logo'=>'https://img.media.jio.com/tvpimages/98/48/301989_1749664475828_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_Select_2_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 HD',        'id'=>'1106',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1106'], 'logo'=>'https://img.media.jio.com/tvpimages/76/32/301981_1749665664018_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_HD1_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1',           'id'=>'1142',   'category'=>'Sports',         'keyId'=>'df20894ec30954d3b28097c138f4cfda','key'=>'ff9b6292b1cddbd11a046029cb314634','logo'=>'https://img.media.jio.com/tvpimages/56/92/302115_1749665579801_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_BTS/WDVLive/index.mpd'],
    ['name'=>'Zee Cinema',              'id'=>'484',    'category'=>'Movies',         'keyId'=>'90d8713ba5e85d779d005b1f14a5bc8d','key'=>'33650314fe1e945017640ba9878c5de9','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/ZeeCinema.png',            'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/ZeeCinema_BTS/WDVLive/index.mpd'],

    ['name'=>'Star Sports 1 Hindi HD',  'id'=>'1108',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1108'], 'logo'=>'https://img.media.jio.com/tvpimages/5/6/301982_1749665314605_l_medium.jpg',   'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_HD1_Hindi_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 Hindi',     'id'=>'362',    'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['362'],  'logo'=>'https://img.media.jio.com/tvpimages/13/81/302112_1749665154709_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_Hindi_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 HD',        'id'=>'1109',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1109'], 'logo'=>'https://img.media.jio.com/tvpimages/41/51/301983_1749666169105_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_HD2_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2',           'id'=>'1141',   'category'=>'Sports',         'keyId'=>'fc3aafe80845584199a637249fad2ffa','key'=>'ca4474972fdb4bf8ecb164482853c692','logo'=>'https://img.media.jio.com/tvpimages/11/30/302116_1749666029079_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_2_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Hindi HD',  'id'=>'1984',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1984'], 'logo'=>'https://img.media.jio.com/tvpimages/56/36/301229_1742214332015_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/StarSportsHD2Hin_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Hindi',     'id'=>'1985',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1985'], 'logo'=>'https://img.media.jio.com/tvpimages/57/17/302124_1749130246443_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/StarSports2Hin_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 3',           'id'=>'1389',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1389'], 'logo'=>'https://img.media.jio.com/tvpimages/78/2/302117_1749666092192_l_medium.jpg',  'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_3_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 Tamil HD',  'id'=>'3277',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['3277'], 'logo'=>'https://img.media.jio.com/tvpimages/19/19/302122_1749662923298_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_Tamil_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 Tamil',     'id'=>'1124',   'category'=>'Sports',         'keyId'=>'d0d750ebd52b5756aa7fb833f61ceece','key'=>'4ae62a20c030c918aad24193c1dda229','logo'=>'https://img.media.jio.com/tvpimages/69/73/301995_1749664384503_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_Tamil_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 Telugu HD', 'id'=>'3278',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['3278'], 'logo'=>'https://img.media.jio.com/tvpimages/19/57/302123_1749663029798_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_Telugu_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 Telugu',    'id'=>'1651',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1651'], 'logo'=>'https://img.media.jio.com/tvpimages/26/67/302119_1749662981095_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_Telugu_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 1 Kannada',   'id'=>'1650',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1650'], 'logo'=>'https://img.media.jio.com/tvpimages/13/91/302118_1749663894692_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_1_Kannada_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Kannada',   'id'=>'1122',   'category'=>'Sports',         'keyId'=>'7af2add2968a56309519ac2680c18f25','key'=>'f992a14e0def17ccdfe16f58c4ce56c5','logo'=>'https://img.media.jio.com/tvpimages/11/37/301993_1749664589518_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports_2_Kannada_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Tamil HD',  'id'=>'3273',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['3273'], 'logo'=>'https://img.media.jio.com/tvpimages/50/43/302120_1749664850896_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports2_Tamil_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Tamil',     'id'=>'2853',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['2853'], 'logo'=>'https://img.media.jio.com/tvpimages/74/92/301561_1749664725619_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/StarSports2Tamil_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Telugu HD', 'id'=>'3274',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['3274'], 'logo'=>'https://img.media.jio.com/tvpimages/84/67/302121_1749664974599_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Sports2_Telugu_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports 2 Telugu',    'id'=>'2852',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['2852'], 'logo'=>'https://img.media.jio.com/tvpimages/41/94/301562_1749665043898_l_medium.jpg', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/StarSports2Telugu_BTS/WDVLive/index.mpd'],
    ['name'=>'Suvarna',                 'id'=>'1117',   'category'=>'Kannada',        'keyId'=>'fc71d871cbf45818a5b3eb3652d75387','key'=>'added6a0df4d0eab8758b0ed9f54ecf2','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Suvarna.png',            'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Suvarna_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Plus',               'id'=>'1116',   'category'=>'Entertainment',  'keyId'=>'3bba2ffaf08b501796c5d9eb431460a4','key'=>'7a62074c4c46f3b153259526481c02bf','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Plus.png',          'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Plus_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Bharat',             'id'=>'1127',   'category'=>'Entertainment',  'keyId'=>'687cfa6d31645a91ba3c38a9acdfac7c','key'=>'c035ca584ac0a2170b96e2fa4523f3be','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Bharat.png',         'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Bharat_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Gold Select',        'id'=>'1119',   'category'=>'Movies',         'keyId'=>'09706397be655eb6a26c6ba99b65e6d5','key'=>'acb1ebe04e2a29fe098d532d7a506846','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Gold_Select.png',    'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Gold_Select_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Gold 2',             'id'=>'1154',   'category'=>'Movies',         'keyId'=>'2db0cd9cb0685309b48e322c83192bda','key'=>'e13bf7a33eb2e19a8f19568dd383ba6d','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Gold_2.png',         'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Gold_2_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Pravah',             'id'=>'1121',   'category'=>'Marathi',        'keyId'=>'3fb941b7c6fe54fb8f66b19804eff952','key'=>'c1b742ed7b6d4eca1c6bdcb9041d9b80','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Pravah.png',         'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Pravah_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Pravah Picture',     'id'=>'3099',   'category'=>'Marathi',        'keyId'=>'d1644aff68865ecdb884c36a634c008f','key'=>'794529e9d938c592514a1eb9eb706da9','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Pravah_Picture.png', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Pravah_Picture_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Pravah HD',          'id'=>'336',    'category'=>'Marathi',        'keyId'=>'ac5fe0f7b01659a9aba3475523f71ae1','key'=>'b2e3f3af283a309e24eabe6b8681282e','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Pravah_HD.png',      'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Pravah_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Pravah Picture HD',  'id'=>'3100',   'category'=>'Marathi',        'keyId'=>'71f9041eea4954e6acf66f0c50d5ebf9','key'=>'98148dde17f872774f443297745df456','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Pravah_Picture_HD.png','url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Pravah_Picture_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Gold HD',            'id'=>'156',    'category'=>'Movies',         'keyId'=>'ec7ad3786d9e5efa9e0c4aeb3e58c7b7','key'=>'907509ccae7e68b30ae75b5d09fe60ef','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Gold_HD.png',         'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Gold_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Gold',               'id'=>'1125',   'category'=>'Movies',         'keyId'=>'940a0a4080f553cab03ec23a2e80cf7c','key'=>'b162354170b54cb588182eb75681cac0','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Gold.png',            'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Gold_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Gold 2 HD',          'id'=>'3096',   'category'=>'Movies',         'keyId'=>'e6afa4754bc15dd28ab6806f417d319d','key'=>'6a74ef2884813547109ee96098610387','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Gold_2_HD.png',      'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Gold_2_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Gold Select HD',     'id'=>'1113',   'category'=>'Movies',         'keyId'=>'2cb38dd5e6235bd9936ad7a29bc7ffd8','key'=>'281d7b329be4e22d4e0e3e1d508ba2a4','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Gold_Select_HD.png', 'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Gold_Select_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Plus HD',            'id'=>'1132',   'category'=>'Entertainment',  'keyId'=>'6f7b7241b2935a909d389fe318e5baaa','key'=>'65783311644973348f359dc154bb41db','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Plus_HD.png',        'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Plus_HD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Movies',             'id'=>'1115',   'category'=>'English Movies', 'keyId'=>'e705c9330a935a119021d97c7677ce62','key'=>'dd306a57de1715b0e1256d5c1737f485','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Movies.png',          'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Movies_BTS/WDVLive/index.mpd'],
    ['name'=>'And Xplore HD',           'id'=>'300322', 'category'=>'Entertainment',  'keyId'=>'67e52f4bdcfc525898be0446feb7f185','key'=>'8cf5c24b168105bca24b358eb05f6df4','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/AndXploreHD.png',         'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/AndXploreHD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Maa',                'id'=>'1138',   'category'=>'Telugu',         'keyId'=>'6744a252854352acbfe40413a14fca0f','key'=>'1de605f98df36a7417eff631e1a0966a','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Maa.png',            'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Maa_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Maa Movies',         'id'=>'1111',   'category'=>'Telugu',         'keyId'=>'f4c022053f355b4b9dca0093463d7ba0','key'=>'9f06388c48bfb94cff9797e25847d1d1','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Maa_Movies.png',          'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Maa_Movies_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Maa Gold',           'id'=>'1137',   'category'=>'Telugu',         'keyId'=>'516d26778a21587484208637d89ff773','key'=>'77599313595e70e5903151b0592ffa15','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Maa_Gold.png',            'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Maa_Gold_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Maa Music',          'id'=>'1129',   'category'=>'Telugu',         'keyId'=>'80cfb1a6a4b251dd9b9ae92782a35917','key'=>'b4cd88f5d71fe94993dd3476cdd63f33','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Maa_Music.png',          'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Maa_Music_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Vijay',              'id'=>'1130',   'category'=>'Tamil',          'keyId'=>'c5f2861efbc7552ba802dcae4e160359','key'=>'4ea32867828280a4d6f36ea8fbbe38eb','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Vijay.png',           'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Vijay_BTS/WDVLive/index.mpd'],
    ['name'=>'Asianet',                 'id'=>'1118',   'category'=>'Malayalam',      'keyId'=>'6b68a5a175d45f0ba8ed12b3016ea4d8','key'=>'c7b8ce1a3a24e2f9b4d1249d04155c49','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Asianet.png',            'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Asianet_BTS/WDVLive/index.mpd'],
    ['name'=>'Asianet Movies',          'id'=>'1153',   'category'=>'Malayalam',      'keyId'=>'ca83419dfa1b56f794032b271b8f2c84','key'=>'d592520eea361507a63c94bff799dfb6','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Asianet_Movies.png',     'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Asianet_Movies_BTS/WDVLive/index.mpd'],
    ['name'=>'Zee Talkies HD',          'id'=>'1358',   'category'=>'Marathi',        'keyId'=>'784cfecc212a5a8e94c18041bd895d87','key'=>'b8e2a35b093029f203d11285ce07598a','logo'=>'https://jiotvimages.cdn.jio.com/dare_images/images/ZeeTalkiesHD.png',         'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/ZeeTalkiesHD_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Vijay Super',        'id'=>'1131',   'category'=>'Tamil',          'keyId'=>'737e9cc83a8a5a55a002fa3ca457244c','key'=>'a0285e3041ff5f4efae5361e12f759db','logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Vijay_Super.png',        'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/Vijay_Super_BTS/WDVLive/index.mpd'],
    ['name'=>'Star Sports Khel',        'id'=>'1998',   'category'=>'Sports',         'keystring'=>$STAR_SPORTS_KEYS['1998'], 'logo'=>'https://jiotv.catchup.cdn.jio.com/dare_images/images/Sports18_Khel.png',      'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/StarSportsKhel_BTS/WDVLive/index.mpd'],
    ['name'=>'Zee Zest HD',             'id'=>'2757',   'category'=>'Movies',         'keyId'=>'a19c52e13a755afaa457a3c8488f4822','key'=>'89dc06fb26e0fc72e9b9a1226073c8a2','logo'=>'https://jiotvimages.cdn.jio.com/dare_images/images/Zee_Zest_HD.png',          'url'=>'https://jiotvpllive.cdn.jio.com/bpk-tv/ZeeZestHD_BTS/WDVLive/index.mpd'],
];

$transformedIds = [];
foreach ($transformed as $t) {
    if (isset($t['id'])) $transformedIds[(string)$t['id']] = true;
}

foreach ($plliveChannelsFallback as $fb) {
    if (!isset($fb['id'])) continue;
    $fbId = (string)$fb['id'];
    if (isset($transformedIds[$fbId])) continue;

    $cookie = getBestCookie($fbId, $starCookieMap, $cloudplayCookieMap, $m3uCookieMap, null);
    $transformed[] = [
        'id'         => $fbId,
        'name'       => $fb['name'],
        'group'      => $fb['category'] ?? '',
        'logo'       => $fb['logo'] ?? '',
        'user_agent' => USER_AGENT_OUT,
        'type'       => 'dash',
        'mpd_url'    => $fb['url'] ?? '',
        'headers'    => ['cookie' => $cookie],
        'expires_in' => null,
        'clearkey'   => buildClearKey($fb),
    ];
    $transformedIds[$fbId] = true;
}

// ---------- Star Jalsha (id: 1126) ----------
if (!isset($transformedIds['1126'])) {
    $transformed[] = buildStarJalsha($cloudplayCookieMap, $starCookieMap, $m3uCookieMap, USER_AGENT_OUT);
    $transformedIds['1126'] = true;
}

// ---------- Force 5-key comma-separated ClearKey for ALL Star Sports channels ----------
foreach ($transformed as $i => $t) {
    if (!isset($t['id'])) continue;
    $tid = (string)$t['id'];
    if (isset($STAR_SPORTS_KEYS[$tid])) {
        $transformed[$i]['clearkey'] = $STAR_SPORTS_KEYS[$tid];
    }
}

// ---------- Output ----------
corsJson($transformed, 200, ['Cache-Control' => 'public, max-age=60']);


/* ============================================================
 *  Helper functions
 * ============================================================ */

function fetchAll(array $urls, int $timeout = 20): array
{
    $mh      = curl_multi_init();
    $handles = [];

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: ' . USER_AGENT_FETCH,
            ],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 1.0);
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    foreach ($handles as $key => $ch) {
        $body     = curl_multi_getcontent($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);

        if ($err !== '') {
            $results[$key] = ['ok' => false, 'error' => $err, 'status' => 0, 'body' => ''];
        } else {
            $results[$key] = [
                'ok'     => $httpCode >= 200 && $httpCode < 300,
                'status' => $httpCode,
                'body'   => $body ?? '',
                'error'  => null,
            ];
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

function getBestCookie(string $id, array $starMap, array $cloudplayMap, array $m3uMap, ?string $fallback): ?string
{
    if (isset($starMap[$id]))      return $starMap[$id];
    if (isset($cloudplayMap[$id])) return $cloudplayMap[$id];
    if (isset($m3uMap[$id]))       return $m3uMap[$id];
    return $fallback;
}

function extractHdneaCookie(string $url): ?string
{
    $parts = parse_url($url);
    if ($parts === false || empty($parts['query'])) return null;

    parse_str($parts['query'], $q);
    if (empty($q['__hdnea__'])) return null;
    return '__hdnea__=' . $q['__hdnea__'];
}

/**
 * Parse clearkey from any format into a clean "kid:key,kid:key,..." string.
 *
 * Handles:
 *   • string  "kid:key,kid:key,..."    ← new upstream format (pllive merged worker)
 *   • array   ["kid" => "key", ...]    ← legacy upstream / cloudflare format
 *   • null / empty                     ← returns ''
 *
 * Skips all-zero placeholder keys.
 */
function parseClearKey(mixed $raw): string
{
    $pairs = [];

    if (is_string($raw) && $raw !== '') {
        // New format: comma-separated "kid:key" pairs
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) continue;
            $kid = strtolower(trim($parts[0]));
            $key = strtolower(trim($parts[1]));
            if ($kid === '' || $key === '') continue;
            if ($kid === '00000000000000000000000000000000') continue;
            if ($key === 'null' || $kid === 'null') continue;
            $pairs[] = "{$kid}:{$key}";
        }
    } elseif (is_array($raw) && !empty($raw)) {
        // Legacy object format: {"kid": "key", ...}
        foreach ($raw as $kid => $key) {
            $kid = strtolower(trim((string)$kid));
            $key = strtolower(trim((string)$key));
            if ($kid === '' || $key === '') continue;
            if ($kid === '00000000000000000000000000000000') continue;
            if ($key === 'null' || $kid === 'null') continue;
            $pairs[] = "{$kid}:{$key}";
        }
    }

    return implode(',', $pairs);
}

function transformChannel(
    array $ch,
    array $cloudplayCookieMap,
    array $starCookieMap,
    array $m3uCookieMap,
    ?string $mbliveCookie,
    string $userAgentOut
): array {
    $originalUrl    = $ch['url']    ?? ($ch['mpd_url'] ?? '');
    $originalCookie = $ch['cookie'] ?? null;
    $id             = (string)($ch['id'] ?? '');

    // ── ClearKey: string (new) > array (legacy) > keyId+key fallback ──
    $clearkey = '';
    $rawCk    = $ch['clearkey'] ?? null;

    if (!empty($rawCk)) {
        $clearkey = parseClearKey($rawCk);
    }

    // Fallback: build from keyId + key fields (mblive cloudflare channels)
    if ($clearkey === '' && !empty($ch['keyId']) && !empty($ch['key'])
        && $ch['keyId'] !== 'null' && $ch['key'] !== 'null') {
        $clearkey = strtolower(trim($ch['keyId'])) . ':' . strtolower(trim($ch['key']));
    }

    // ── jiotvmblive: use shared mblive cookie, keep URL as-is ─────────
    if (strpos($originalUrl, 'jiotvmblive') !== false) {
        $finalMpdUrl = $originalUrl;
        if (endsWith(strtolower($originalUrl), '.m3u8') && $mbliveCookie) {
            $finalMpdUrl = appendCookieToUrl($originalUrl, $mbliveCookie);
        }
        return [
            'id'         => $id,
            'name'       => $ch['name'] ?? '',
            'group'      => $ch['category'] ?? ($ch['group'] ?? ''),
            'logo'       => $ch['logo'] ?? '',
            'user_agent' => $userAgentOut,
            'type'       => 'dash',
            'mpd_url'    => $finalMpdUrl,
            'headers'    => ['cookie' => $mbliveCookie],
            'expires_in' => null,
            'clearkey'   => $clearkey,
        ];
    }

    // ── jiotvpllive / bpkstb: refresh cookie from live sources ────────
    $cookie = $originalCookie;
    if (strpos($originalUrl, 'jiotvpllive') !== false
        || strpos($originalUrl, 'jiotvbpkstb') !== false) {
        $cookie = getBestCookie($id, $starCookieMap, $cloudplayCookieMap, $m3uCookieMap, $originalCookie);
    }

    $finalMpdUrl = $originalUrl;
    if (endsWith(strtolower($originalUrl), '.m3u8') && $cookie) {
        $finalMpdUrl = appendCookieToUrl($originalUrl, $cookie);
    }

    return [
        'id'         => $id,
        'name'       => $ch['name'] ?? '',
        'group'      => $ch['category'] ?? ($ch['group'] ?? ''),
        'logo'       => $ch['logo'] ?? '',
        'user_agent' => $userAgentOut,
        'type'       => 'dash',
        'mpd_url'    => $finalMpdUrl,
        'headers'    => ['cookie' => $cookie],
        'expires_in' => null,
        'clearkey'   => $clearkey,
    ];
}

function appendCookieToUrl(string $url, string $cookie): string
{
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . $cookie;
}

/**
 * Builds the `clearkey` string for a fallback channel definition.
 *   1. 'keystring'   → raw comma-separated string (multi-key, already correct)
 *   2. 'keys'        → assoc array [kid => key, ...] → "kid:key,..." string
 *   3. 'keyId'+'key' → single pair → "kid:key" string
 *   4. none          → empty string ''
 */
function buildClearKey(array $fb): string
{
    if (!empty($fb['keystring']) && is_string($fb['keystring'])) {
        return $fb['keystring'];
    }
    if (!empty($fb['keys']) && is_array($fb['keys'])) {
        $pairs = [];
        foreach ($fb['keys'] as $kid => $key) {
            $kid = strtolower(trim((string)$kid));
            $key = strtolower(trim((string)$key));
            if ($kid !== '' && $key !== '') $pairs[] = "{$kid}:{$key}";
        }
        return implode(',', $pairs);
    }
    if (!empty($fb['keyId']) && !empty($fb['key'])) {
        return strtolower(trim($fb['keyId'])) . ':' . strtolower(trim($fb['key']));
    }
    return '';
}

function buildStarJalsha(
    array $cloudplayCookieMap,
    array $starCookieMap,
    array $m3uCookieMap,
    string $userAgentOut
): array {
    $fallbackCookie = '__hdnea__=st=1785259827~exp=1785281427~acl=/bpk-tv/Star_Jalsha_BTS/WDVLive/*~hmac=684b0f5ad76436f06903e2513dcf805c0ebd0ae3297bcd586fd8f06e1ac7ee19';
    $cookie = getBestCookie('1126', $starCookieMap, $cloudplayCookieMap, $m3uCookieMap, $fallbackCookie);

    return [
        'id'         => '1126',
        'name'       => 'Star Jalsha',
        'group'      => 'Bengali',
        'logo'       => 'https://jiotv.catchup.cdn.jio.com/dare_images/images/Star_Jalsha.png',
        'user_agent' => $userAgentOut,
        'type'       => 'dash',
        'mpd_url'    => 'https://jiotvpllive.cdn.jio.com/bpk-tv/Star_Jalsha_BTS/WDVLive/index.mpd',
        'headers'    => ['cookie' => $cookie],
        'expires_in' => null,
        // String format: "kid:key"
        'clearkey'   => '9d4fac613fa6505c8f4c34269bad472a:4eb0380c9f3d517db1eeaa768a3e23ac',
    ];
}

function endsWith(string $haystack, string $needle): bool
{
    if ($needle === '') return true;
    return substr($haystack, -strlen($needle)) === $needle;
}

function corsJson(mixed $data, int $status = 200, array $extraHeaders = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    foreach ($extraHeaders as $k => $v) {
        header("$k: $v");
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
