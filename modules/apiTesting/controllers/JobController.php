<?php

namespace app\modules\apiTesting\controllers;

use app\modules\apiTesting\models\ApiTestJob;
use app\modules\apiTesting\models\ApiTestJobSchedule;
use app\modules\apiTesting\models\ApiTestJobSearch;
use app\modules\apiTesting\models\ApiTestProject;
use app\modules\apiTesting\services\JobService;
use app\modules\apiTesting\services\RunnerQueueService;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * JobController implements the CRUD actions for ApiTestJob model.
 * @property JobService $jobService
 * @property RunnerQueueService $runnerQueueService
 */
class JobController extends Controller
{
    private $jobService;
    private $runnerQueueService;

    public function init()
    {
        parent::init();
        $this->jobService = new JobService();
        $this->runnerQueueService = new RunnerQueueService();
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all ApiTestJob models.
     * @return mixed
     */
    public function actionIndex($id)
    {
        $searchModel = new ApiTestJobSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $project = $this->findProject($id);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'project' => $project,
        ]);
    }

    /**
     * Displays a single ApiTestJob model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new ApiTestJob model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate($id)
    {
        $project = $this->findProject($id);
        $model = new ApiTestJob([
            'project_id' => $project->id
        ]);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
            'project' => $project
        ]);
    }

    /**
     * Updates an existing ApiTestJob model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing ApiTestJob model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    public function actionRequestsManage($id)
    {
        $job = $this->findModel($id);

        if (Yii::$app->request->isPost && $job->load(Yii::$app->request->post()) && $this->jobService->update($job)) {
            $this->redirect(['view', 'id' => $job->id]);
        }

        return $this->renderAjax('requests_manage', [
            'job' => $job
        ]);
    }

    public function actionCreateSchedule($id)
    {
        $job = $this->findModel($id);

        $schedule = new ApiTestJobSchedule();

        if (Yii::$app->request->isPost && $schedule->load(Yii::$app->request->post()) && $this->jobService->addSchedule($job, $schedule)) {
            $this->redirect(['view', 'id' => $job->id]);
        }

        return $this->render('schedule_form', [
            'schedule' => $schedule,
            'project' => $job->project,
            'job' => $job
        ]);
    }

    public function actionUpdateSchedule($id)
    {
        $schedule = ApiTestJobSchedule::findOne($id);

        if ( ! $schedule) {
            throw  new NotFoundHttpException();
        }

        if (Yii::$app->request->isPost && $schedule->load(Yii::$app->request->post()) && $this->jobService->addSchedule($schedule->job, $schedule)) {
            $this->redirect(['view', 'id' => $schedule->job->id]);
        }

        return $this->render('schedule_form', [
            'schedule' => $schedule,
            'project' => $schedule->job->project,
            'job' => $schedule->job
        ]);
    }

    public function actionDeleteSchedule($id)
    {
        $schedule = ApiTestJobSchedule::findOne($id);
        $this->findProject($schedule->job->project->id);
        $schedule->delete();
        return $this->redirect(Yii::$app->request->referrer);
    }

    public function actionRun($id)
    {
        $job = $this->findModel($id);
        $project = $this->findProject($job->project->id);
        $this->runnerQueueService->addJobToQueue($job);
        return $this->redirect(['/apiTesting/runner', 'id' => $project->id]);
    }

    /**
     * Finds the ApiTestJob model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return ApiTestJob the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = ApiTestJob::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    protected function findProject($id)
    {
        if (($model = ApiTestProject::find()->andWhere(['id' => $id])->my()->one()) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}