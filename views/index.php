<?php
/** @var SurveyJs $model */
/** @var Survey $survey */
?>

<script src="https://unpkg.com/jquery"></script>
<script src="https://surveyjs.azureedge.net/1.8.14/survey.jquery.js"></script>
<link href="https://surveyjs.azureedge.net/1.8.14/modern.css" type="text/css" rel="stylesheet"/>

<div class="page-header">
    <span class="h1">Export SurveyJs JSON</span>
</div>
<pre>
    <?= CHtml::encode($model->getJson(true))?>
</pre>

<div id="surveyElement" style="display:inline-block;width:100%;"></div>
<div id="surveyResult"></div>

<script type="text/javascript">


    Survey
        .StylesManager
        .applyTheme("modern");

    var json = <?=$model->getJson()?>;

    window.survey = new Survey.Model(json);

    survey
        .onComplete
        .add(function (result) {
            document
                .querySelector('#surveyResult')
                .textContent = "Result JSON:\n" + JSON.stringify(result.data, null, 3);
        });
    //survey.currentPageNo = 4;

    $("#surveyElement").Survey({model: survey});


</script>

