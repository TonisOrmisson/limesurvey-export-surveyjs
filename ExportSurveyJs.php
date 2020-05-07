<?php
/**
 * @author Tõnis Ormisson <tonis@andmemasin.eu>
 */
class ExportSurveyJs extends PluginBase {

    /** @var LSYii_Application */
    protected $app;

    protected $storage = 'DbStorage';
    static protected $description = 'Export survey as SurveyJs JSON';
    static protected $name = 'Export SurveyJs';

    /** @var array  */
    private $data = [];

    /** @var Survey $survey */
    private $survey;


    /** @var string */
    public $type;

    /* Register plugin on events*/
    public function init() {
        $this->subscribe('beforeToolsMenuRender');
        $this->app = Yii::app();
    }

    public function beforeToolsMenuRender() {
        $event = $this->getEvent();

        /** @var array $menuItems */
        $menuItems = $event->get('menuItems');
        $this->survey = Survey::model()->findByPk($event->get('surveyId'));

        $menuItem = new \LimeSurvey\Menu\MenuItem([
            'label' => $this->getName(),
            'href' => $this->createUrl('actionIndex'),
            'iconClass' => 'fa fa-tasks  text-info',

        ]);
        $menuItems[] = $menuItem;
        $event->set('menuItems', $menuItems);
        return $menuItems;

    }

    /**
     * @param string $action
     * @param array $params
     * @return string
     */
    private function createUrl($action, $params = []) {
        $url = $this->api->createUrl(
            'admin/pluginhelper',
            array_merge([
                'sa'     => 'sidebody',
                'plugin' => 'ExportSurveyJs',
                'method' => $action,
                'sid' => $this->survey->primaryKey,
            ], $params)
        );
        return $url;
    }



    public function actionIndex($sid)
    {
        $import = null;
        $this->data['exportPlugin'] = $this;
        require_once __DIR__.DIRECTORY_SEPARATOR.'SurveyJs.php';
        $this->survey = Survey::model()->findByPk($sid);
        $model = new SurveyJs($this->survey);
        //$model->usePages = false;
        $model->populate();
        $this->data['model'] = $model;


        return $this->renderPartial('index', $this->data, true);
    }



}
