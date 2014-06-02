<?
$_SERVER['DOCUMENT_ROOT'] = dirname(__FILE__) . '/../';
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("LANG", "ru");
define('SITE_ID', 's1');
define("FORMAT_DATE", "DD.MM.YYYY");
define("FORMAT_DATETIME", "DD.MM.YYYY HH:MI:SS");
define("BX_UTF", true);
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("BX_BUFFER_USED", true);
define('BX_NO_ACCELERATOR_RESET', true);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// ошибки
ini_set('show_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
// сброс буферизации
while (ob_get_level()) {
	ob_end_flush();
}
ini_set("max_execution_time", "0"); // анлимит на время исполнения
set_time_limit(0); // анлимит на время исполнения



/**
 * Глубина логирования, 0 - silent
 */
define('S_LOG_LEVEL', 1);
/**
 * Товары отложенные в корзине ранее $DAYS_AGO дней назад не выводить
 */
$DAYS_AGO = 30;
/**
 * ID Типа почтового сообщения
 */
$EVENT_ID = 'CART_SUBSCRIBER';
/**
 * Имя хоста
 */
$HOST_NAME;

$rsSite = CSite::GetByID(SITE_ID);
if ($arSite = $rsSite->Fetch()) {
	$HOST_NAME = 'http://' . $arSite['SITE_NAME'];
} else {
	$HOST_NAME = 'http://gu.ru';
}


$USERS = array(1);
/**
 * Корзины пользователя array( 'USER_ID' => array( '0 => array(), 1 => array() ) )
 */
$usersBasket = array();
/**
 * карта продуктов корзины ( 'PRODUCT_ID' => array( описание элемента и.б. ) )
 */
$arProducts = array();
/**
 * карта пользователей ( 'USER_ID' => array( описание пользователя ) )
 */
$arUsers = array();
/**
 * Месяц назад (Дата в формате сайта)
 */
$dateMonthAgo = ConvertTimeStamp(mktime(0, 0, 0, date('n'), date('j') - $DAYS_AGO, date('Y')));

// модули
\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('sale');

// Пользователи
$rsUsers = CUser::GetList(
	($by = "id"),
	($order = "asc"),
	array('!GROUPS_ID' => array(1))
);
while ($arUser = $rsUsers->Fetch()) {
	$arUsers[ $arUser['ID'] ] = $arUser;
}

$rsOrderBasket = CSaleBasket::GetList(array('SORT' => 'ASC'),
	array(
		'USER_ID' => array_keys($arUsers),
		'!=ORDER_ID' => false,
		'>DATE_INSERT' => $dateMonthAgo,
		'DELAY' => 'Y'
	),
	false,
	false,
	array('PRODUCT_ID', 'USER_ID', 'DATE_UPDATE')
);
while($arOrderBasket = $rsOrderBasket->Fetch()){
	$arUserBasketOrdered[ $arOrderBasket['USER_ID'] ][] = $arOrderBasket['PRODUCT_ID'];
}

// Раннее заказанные товары пользователей
$rsBasket = CSaleBasket::GetList(
	array('SORT' => 'ASC'),
	array(
		'USER_ID' => array_keys($arUsers),
		'>DATE_UPDATE' => $dateMonthAgo,
		'DELAY' => 'Y'
	),
	false,
	false,
	array('PRODUCT_ID', 'USER_ID', 'DATE_UPDATE')
);
while ($arBasket = $rsBasket->Fetch()) {
	$arProducts[$arBasket['PRODUCT_ID']] = true;
	if (!in_array($arBasket['PRODUCT_ID'], $arUserBasketOrdered)) {
		$usersBasket[$arBasket['USER_ID']][] = $arBasket['PRODUCT_ID'];
	}
}

// Продукты корзины
if (!empty($arProducts)) {
	$rsEl = CIBlockElement::GetList(
		array('SORT' => 'ASC'),
		array('ID' => array_keys($arProducts))
	);
	while ($arEl = $rsEl->GetNext()) {
		$arProducts[$arEl['ID']] = $arEl;
	}
}

// сборка сообщений
foreach ($usersBasket as $user_id => $products) {
	$ar_user = $arUsers[$user_id];
	foreach ($products as $prod_id) {
		$ar_product = $arProducts[$prod_id];
		$productList[] = '<a href="' . $HOST_NAME . $ar_product['DETAIL_PAGE_URL'] . '">' . $ar_product['NAME'] . '</a>';
	}
	$fullName = array_filter(array($ar_user['NAME'], $ar_user['LAST_NAME']));
	if (empty($fullName)) {
		$fullName = array($ar_user['EMAIL']);
	}
	$arMessFields[] = array(
		'EMAIL' => $ar_user['EMAIL'],
		'USER_NAME' => implode(', ', $fullName),
		'PRODUCT_LIST' => implode("<br>\n", $productList),
	);
}

// отправка
foreach ($arMessFields as $arFields) {
	CEvent::Send($EVENT_ID, SITE_ID, $arFields);
}

if(S_LOG_LEVEL){
	echo "\n\$arMessFields:\n"; print_r($arMessFields); echo "\n";
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
?>
