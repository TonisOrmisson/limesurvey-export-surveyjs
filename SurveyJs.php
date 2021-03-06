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
        $this->questions = $this->survey->baseQuestions;
        $this->variablePregs();
        $this->array = [
            'locale' => $this->displayLanguage,
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

        foreach ($this->survey->groups as $group) {
            $this->questions = $group->questions;
            $out[] = [
                'name' => $group->group_name,
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
        if (!$this->usePages) {
            $out[] = $this->populateLanguageSwitch();
        }

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
            'title' => $l10n->question . " :" . $this->question->type . " " . count($this->question->subquestions),
            'isRequired' => ($this->question->mandatory =="Y"),
            'description' => $this->parseRelevance()
        ];

        if($this->enableFilters) {
            $question['visibleIf'] = $this->parseRelevance();
        }

        switch ($this->question->type) {

            case QuestionType::QT_Z_LIST_RADIO_FLEXIBLE:
            case QuestionType::QT_L_LIST_DROPDOWN:
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
                'value' => $answer->code,
                'text' => $translations[$this->displayLanguage]->answer,
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
                'value' => $subQuestion->title,
                'text' => $translations[$this->displayLanguage]->question,
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
            case QuestionType::QT_L_LIST_DROPDOWN:
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
     * @return mixed|string
     */
    private function parseRelevance(){
        $r = $this->question->relevance;
        $statics = [
            '==' => "=",
            '!==' => "!=",
            '.NAOK' => "",
            '&&' => "and",
        ];
        $pregs = $this->variablePregs;


        $r = str_replace(array_keys($statics), array_values($statics), $r);

        $r = str_replace(array_keys($pregs), array_values($pregs), $r);
        return $r;
    }

    /**
     * @return array
     */
    private function variablePregs(){
        foreach ($this->questions as $question) {
            $this->variablePregs[$question->title] = "{".$question->title."}";
            //TODO need to do answers & subquestions BRFORE that
            $this->variablePregs[$this->sgqa($question)] = "{".$question->title."}";
        }
    }

    /**
     * @return array
     */
    private function populateChoices() {
        $out = [];

        switch ($this->question->type) {
            case QuestionType::QT_Z_LIST_RADIO_FLEXIBLE:
            case QuestionType::QT_L_LIST_DROPDOWN:
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
            $translations = $subQuestion->questionl10ns;

            $subQuestion = [
                'value' => $subQuestion->title,
                'text' => $translations[$this->displayLanguage]->question,
            ];
            $out[] = $subQuestion;
        }
        return $out;
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
            $translations = $answer->answerl10ns;

            $answer = [
                'value' => $answer->code,
                'text' => $translations[$this->displayLanguage]->answer,
            ];
            $out[] = $answer;
        }
        return $out;
    }


    private function sgqa(Question $question) {
        $sgq = $question->sid . "X" . $question->gid . "X" . $question->qid;
        return $sgq;
    }

    /**
     * @return false|string
     */
    public function getJson(){
        return json_encode($this->array);
    }



}
