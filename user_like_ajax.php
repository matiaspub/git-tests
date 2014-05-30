<? if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$IBLOCK_ID = 5;

if ($USER_ID > 0 && $ELEMENT_ID > 0) {
	\Bitrix\Main\Loader::includeModule('iblock');

	//region Старые значения множественного свойства
	$arPropValues = array();
	$rsProps = CIBlockElement::GetProperty(
		$IBLOCK_ID,
		$ELEMENT_ID,
		array("sort" => "asc"),
		array("CODE" => "RECOMMEND")
	);
	while ($arProp = $rsProps->Fetch()) {
		// убираем юзера - если АНлайкнул
		if ($arProp['VALUE'] == $USER_ID) {
			continue;
		}
		$arPropValues['n' . sizeof($arPropValues)] = $arProp['VALUE'];
	}
	//endregion

	//region Добавляем юзера - если лайкнул
	if ((int)$LIKE > 0) {
		$arPropValues['n0' . sizeof($arPropValues)] = $USER_ID;
	}
	//endregion

	CIBlockElement::SetPropertyValuesEx(
		$ELEMENT_ID,
		$IBLOCK_ID, // IBLOCK_ID
		array('RECOMMEND' => $arPropValues)
	);

	echo 'ok';
} else {
	echo "error";
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
}
?>
