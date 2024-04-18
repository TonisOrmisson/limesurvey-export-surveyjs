<?php
require_once __DIR__ . DIRECTORY_SEPARATOR.'vendor/autoload.php';
use League\HTMLToMarkdown\HtmlConverter;

class SurveyJs
{
    /** @var Survey */
    private $survey;


    /** @var array */
    private $array = [];

    /** @var Question[] */
    private $questions;

    /** @var Question */
    private $question;

    /** @var QuestionL10n[] */
    private $questionl10ns;

    /**
     * @var string[]
     */
    private $surveyLanguages = [];

    private $variablePregs = [];


    /** @var HtmlConverter */
    private $markupConverter;

    public $enableFilters = true;

    public $usePages = true;

    private $displayLanguage = '';

    /**
     * SurveyJs constructor.
     * @param Survey $survey
     */
    public function __construct(Survey $survey)
    {
        $this->survey = $survey;
        $this->surveyLanguages = $survey->allLanguages;
        $this->markupConverter = new HtmlConverter(['strip_tags' => true]);
        $this->displayLanguage = $survey->language;
    }



    public function populate() {
        $this->questions = $this->survey->getBaseQuestions();
        $this->variablePregs();
        $this->array = [
            'locale' => $this->displayLanguage,
            'defaultLocale' => $this->displayLanguage,
        ];

        if($this->usePages) {
            $this->array['pages'] = $this->populateGroups();
            return;
        }
        $this->array['questions'] = $this->populateQuestions();
        return;
    }

    private function populateGroups() {
        $out = [];

        $languageSwitch = $this->populateLanguageSwitch();
        if(!empty($languageSwitch)) {
            $out[] = [
                'name' => "language-first-page",
                'elements' => [$languageSwitch]
            ];
        }

        foreach ($this->survey->groups as $group) {
            $this->questions = $this->groupBaseQuestions($group);
            $out[] = [
                'name' => $this->removeScriptsAndLineBreaks($group->group_name),
                'elements' => $this->populateQuestions(),
            ];
        }
        return $out;
    }

    /**
     * @return array
     */
    private  function  populateQuestions() {
        $out = [];

        foreach ($this->questions as $question) {
            $this->question = $question;
            $out[] = $this->populateQuestion();
        }
        return $out;
    }

    /**
     * @return array
     */
    private function populateQuestion() {
        $this->questionl10ns = $this->question->questionl10ns;
        $l10n = $this->questionl10ns[$this->displayLanguage];
        $question = [
            'name' => $this->question->title,
            'title' => $this->populateQuestionTexts($this->question),
            'isRequired' => ($this->question->mandatory =="Y"),
            'description' => $this->parseRelevance()
        ];

        if($this->enableFilters) {
            if(!empty($relevance)) {
                $question['visibleIf'] = $relevance;
                $relevance = $this->parseRelevance();
            }

        }

        switch ($this->question->type) {

            case QuestionType::QT_L_LIST:
                $question = $this->populateSingleQuestion($question);
                break;
            case QuestionType::QT_N_NUMERICAL:
                $question = $this->populateNumericQuestion($question);
                break;
            case QuestionType::QT_F_ARRAY_FLEXIBLE_ROW:
                $question = $this->populateMatrixQuestion($question);
                break;
            case QuestionType::QT_M_MULTIPLE_CHOICE:
                $question = $this->populateMultiQuestion($question);
                break;
            default:
                $question['type'] = 'text';
        }

        return $question;
    }

    /**
     * @return array
     */
    private function populateLanguageSwitch() {
        if(count($this->surveyLanguages) === 1) {
            return [];
        }

        $data = [
            'name' => 'language',
            'title' => Yii::t('app', "What is your preferred language?"),
            'type' => 'radiogroup',
            'isRequired' => true,
            'hideNumber' => true,
            'choices' => [],
        ];

        foreach ($this->surveyLanguages as $language) {
            $data['choices'][] = ['value' => $language, 'text' => $language];
        }
        return $data;

    }


    private function populateNumericQuestion(array  $data) {
        $data['type'] = 'text';
        $data['inputType'] = 'number';
        $data['validators'] = [
            [
                'type'=> "numeric",
            ]
        ];
        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    private function populateMatrixQuestion(array  $data) {
        $data['type'] = 'matrix';
        $data['columnMinWidth'] = '200px';
        $out =[];

        $answers = $this->question->answers;
        foreach ($answers as $answer) {
            $translations = $answer->answerl10ns;

            $answer = [
                'value' => $this->parseIntIfPossible($answer->code),
                'text' => $this->removeScriptsAndLineBreaks($translations[$this->displayLanguage]->answer),
                'maxWidth' => "1%"
            ];
            $out[] = $answer;
        }

        $data['columns'] = $out;

        $out = [];
        $subQuestions = $this->question->subquestions;
        foreach ($subQuestions as $subQuestion) {
            $translations = $subQuestion->questionl10ns;

            $subQuestion = [
                'value' => $this->parseIntIfPossible($subQuestion->title),
                'text' => $this->removeScriptsAndLineBreaks($translations[$this->displayLanguage]->question),
            ];
            $out[] = $subQuestion;
        }

        $data['rows'] = $out;

        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    private function populateSingleQuestion(array  $data) {
        switch ($this->question->type) {
            case QuestionType::QT_L_LIST:
                $data['type'] = 'radiogroup';
                break;
            case QuestionType::QT_EXCLAMATION_LIST_DROPDOWN:
                $data['type'] = 'dropdown';
                break;
        }
        $data['hasOther'] = true;
        $data['choices'] = $this->populateChoices();
        return $data;
    }

    private function populateMultiQuestion(array  $data) {
        $data['type'] = 'checkbox';
        $data['choices'] = $this->populateChoices();
        return $data;
    }

    /**
     * @return mixed|string|null
     */
    private function parseRelevance()
    {
        $r = $this->question->relevance;

        if($r == "1") {
            return null;
        }
        $statics = [
            '==' => "=",
            '!==' => "!=",
            '.NAOK' => "",
            '&&' => "and",

            // TODO this needs to be replaced by "{var} empty" etc
            '!is_empty(' => "(",
            'is_empty(' => "(",
            '"' => "'",
        ];


        $r = str_replace(array_keys($statics), array_values($statics), $r);
//        ini_set('xdebug.var_display_max_children', 3000 );
//        xdebug_var_dump($this->variablePregs);die;
        // since varNames might be overlapping, we need to do this in order, not as array
        foreach($this->variablePregs as $needle => $replacement) {
            // skip all {narNames} that might be already replaced earlier
            $pattern = sprintf('/{[^}]+}(*SKIP)(*F)|%s/', preg_quote($needle, '/'));
            $r = preg_replace($pattern, $replacement, $r);

        }
        return $r;
    }

    /**
     * @return array
     */
    private function variablePregs(){
        foreach ($this->questions as $question) {

            if(isset($this->variablePregs[$question->title])) {
                continue;
            }

            // if overlapping names exist, they need to be prior to shorter ones
            $longerOverLappingVarNames = $this->longerOverLapNames($question->title, $this->questionVarNames());
            foreach ($longerOverLappingVarNames as $lappingVarName) {
                $this->variablePregs[$lappingVarName] = "{".$lappingVarName."}";
            }



            $subQuestions = $question->subquestions;
            foreach ($subQuestions as $subQuestion) {
                $tag = $question->title."_".$subQuestion->title;
                $this->variablePregs[$tag] = "{".$tag."}";
                $this->variablePregs[$this->sgqa($subQuestion)] = "{".$question->title."_".$subQuestion->title."}";
            }

            foreach ($question->answers as $answer) {
                $tag = $question->title."_".$answer->code;
                $this->variablePregs[$tag] = "{".$tag."}";
            }

            $longerOverLappingSGQAs = $this->longerOverLapNames($question->title, $this->questionSGQAs());
            foreach ($longerOverLappingSGQAs as $lappingVarName) {
                $this->variablePregs[$lappingVarName] = "{".$lappingVarName."}";
            }


            //TODO need to do answers & subquestions BRFORE that
            $this->variablePregs[$question->title] = "{".$question->title."}";
            $this->variablePregs[$this->sgqa($question)] = "{".$question->title."}";
        }

    }

    private function longerOverLapNames(string $varName, array $hayStack) {

        $result = array_filter($hayStack,fn($val) => str_contains($val, $varName));
        //only vars that are longer than itself is
        $result = array_filter($result,fn($val) => strlen($val) > strlen($varName));
        return $result;
    }

    private function questionVarNames() : array
    {
        $out = [];
        foreach ($this->questions as $question) {
            $out[] = $question->title;
        }
        return $out;
    }


    private function questionSGQAs() : array
    {
        $out = [];
        foreach ($this->questions as $question) {
            $out[] = $this->sgqa($question);
        }
        return $out;
    }


    /**
     * @return array
     */
    private function populateChoices() {
        $out = [];

        switch ($this->question->type) {
            case QuestionType::QT_L_LIST:
                $out = $this->populateListChoices();
                break;
            case QuestionType::QT_M_MULTIPLE_CHOICE:
                $out = $this->populateMultiChoices();
                break;
        }
        return $out;

    }


    /**
     * @return array
     */
    public function populateMultiChoices() {
        $out = [];
        $subQuestions = $this->question->subquestions;
        if (empty($subQuestions)) {
            return $out;
        }

        foreach ($subQuestions as $subQuestion) {

            $subQuestion = [
                'value' => 1, // not using 'Y' here
                'text' => $this->populateQuestionTexts($subQuestion),
            ];
            $out[] = $subQuestion;
        }
        return $out;
    }

    private function isInt($value) {
        return is_numeric($value) && floatval(intval($value)) === floatval($value);
    }

    private function parseIntIfPossible(string $value) {
        if($this->isInt($value)) {
            return intval($value);
        }
        return $value;
    }

    /**
     * @return array
     */
    private function populateListChoices() {
        $out = [];
        $answers = $this->question->answers;
        if (empty($answers)) {
            return $out;
        }

        foreach ($answers as $answer) {
            $answer = [
                'value' => $this->parseIntIfPossible($answer->code),
                'text' => $this->populateAnswerTexts($answer),
            ];
            $out[] = $answer;
        }
        return $out;
    }

    private function populateQuestionTexts(Question $question) {
        $translations = $question->questionl10ns;
        $texts = [
            'default' => $this->removeScriptsAndLineBreaks($translations[$this->displayLanguage]->question),
        ];
        foreach ($this->surveyLanguages as $language) {
            if(isset($translations[$language]) && $translations[$language] instanceof QuestionL10n) {
                $texts[$language] = $this->removeScriptsAndLineBreaks($translations[$language]->question);
            }
        }
        return $texts;
    }

    private function populateAnswerTexts(Answer $answer) {
        $translations = $answer->answerl10ns;
        $texts = [
            'default' => $this->removeScriptsAndLineBreaks($translations[$this->displayLanguage]->answer),
        ];
        foreach ($this->surveyLanguages as $language) {
            if(isset($translations[$language]) && $translations[$language] instanceof AnswerL10n) {
                $texts[$language] = $this->removeScriptsAndLineBreaks($translations[$language]->answer);
            }
        }
        return $texts;
    }


    private function sgqa(Question $question) : string
    {
        return$question->sid . "X" . $question->gid . "X" . $question->qid;
    }

    /**
     * @return false|string
     */
    public function getJson(bool $pretty=false) : string
    {
        if($pretty) {
            $result =  json_encode($this->array, JSON_PRETTY_PRINT);
        } else {
            $result =  json_encode($this->array);
        }
        if(!$result) {
            return "{}";
        }
        return $result;
    }

    private function groupBaseQuestions(QuestionGroup $group) {
        $out = [];
        foreach ($group->questions as $question) {

            if(empty($question->parent_qid)) {
                $out[] = $question;
            }
        }
        return $out;
    }

    private function removeScriptsAndLineBreaks(?string $input) : string
    {
        if($input === null) {
            return "";
        }
        $remove  = ["\n\r", "\n", "\r", "\t"];
        $input = str_replace($remove, "", $input);
        return preg_replace("/<script.*?\/script>/s", "", $input);
    }



}
