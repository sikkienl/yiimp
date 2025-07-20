<?php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\helpers\Html;

class ViewUtils extends Component
{
public function showButtonHeader()
{
	echo "<div class='buttonwrapper'>";
}

public function showButton($name, $link, $htmlOptions = array())
{
	echo Html::a($name, $link, $htmlOptions);
}

public function showButtonPost($name, $htmlOptions)
{
	echo Html::submitButton($name, $htmlOptions);
}

public function showTextTeaser($text, $more, $count = 120, $class = 'text')
{
	if(empty($text)) return "";

	$text = strip_tags($text);
	if(strlen($text) < $count)
	{
		echo "<p class='$class'>$text</p>";
		return;
	}

	$text = substr($text, 0, $count)."...";
	echo "<p class='$class'>".$text." [".Html::a("more...", $more)."]</p>";
}

public function getTextTeaser($text, $count = 120)
{
	if(empty($text)) return "";

	$text = strip_tags($text);
	if(strlen($text) < $count)
		return $text;

	$text = substr($text, 0, $count)."...";
	return $text;
}

public function getTextTitle($text)
{
	$b = preg_match('/([^\.\r\n]*)/', $text, $match);
	return $match[1];
}

public function showTableSorter($id, $options='')
{
	$this->JavascriptReady("
		$('#{$id}').tablesorter({$options});
		$('.tablesorter-header').not('.sorter-false').css('cursor', 'pointer');"
	);
	echo "<table id='$id' class='dataGrid2'>";
}
public function JavascriptFile($filename)
{
	echo Html::jsFile($filename);
}

public function Javascript($javascript)
{
	echo "<script>$javascript</script>";
}

public function JavascriptReady($javascript)
{
	echo "<script>$(function(){ $javascript})</script>";
}

}