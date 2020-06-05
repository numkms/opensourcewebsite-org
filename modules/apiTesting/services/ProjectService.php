<?php

namespace app\modules\apiTesting\services;

use app\models\Setting;
use app\models\User;
use app\modules\apiTesting\models\ApiTestProject;
use app\modules\apiTesting\models\ApiTestTeam;
use app\modules\apiTesting\models\ApiTestTeamSearch;
use phpDocumentor\Reflection\Types\Boolean;
use SebastianBergmann\CodeCoverage\Report\Xml\Project;
use Yii;
use yii\base\Exception;

/**
 * Class ProjectService
 *
 * @package app\modules\apiTesting\services
 * @property ProjectTeamService $teamService
 */
class ProjectService extends \yii\base\Component
{
    private $teamService;

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $this->teamService = new ProjectTeamService();
    }

    /**
     * @param ApiTestProject $projectModel
     * @param array $withNewData
     * @param boolean $withoutFormName
     * Сохраняет данные о проекте в базу данных, подставляет текущего пользователя, можно подставлять данные чистым массивом без formName
     * При помощи аргумента withoutFormName
     * @return bool
     */
    public function save(ApiTestProject $projectModel, array $withNewData, $withoutFormName = false): bool
    {
        if ($projectModel->isNewRecord && ! $this->checkIfCanCreateProject($projectModel)) {
            return false;
        }

        if ($projectModel->load($withNewData, $withoutFormName ? '' : $projectModel->formName())) {
            $this->setUserIfNeeded($projectModel);
            try {
                $transaction = Yii::$app->db->beginTransaction();
                if ( ! $projectModel->hasErrors() && $projectModel->save()) {
                    $this->addCreatorToTeam($projectModel);
                    $transaction->commit();
                    return true;
                }
            } catch (\Exception $exception) {
                $transaction->rollBack();
                throw new \yii\db\Exception($exception->getMessage());
            }
        }

        return false;
    }

    public function checkIfCanCreateProject(ApiTestProject $projectModel)
    {
        if ( ! $this->checkUserRatingRating()) {
            $projectModel->addError('name', 'You can\'t create project cause your rating too small for it');
            return false;
        }

        return true;
    }

    public function checkUserRatingRating()
    {
        /** @var $identity User */
        $identity = Yii::$app->user->identity;
        $projectCount = $identity->getProjects()->count() + 1;
        if ($projectCount > $identity->getMaxProjectsCount()) {
            return false;
        }
        return true;
    }

    private function setUserIfNeeded($projectModel)
    {
        if ($projectModel->isNewRecord) {
            $projectModel->user_id = Yii::$app->user->id;
        }
    }

    private function addCreatorToTeam($project)
    {
        $team = new ApiTestTeam([
            'project_id' => $project->id,
            'user_id' => Yii::$app->user->id
        ]);

        $this->teamService->acceptInvite($team);
    }
}
