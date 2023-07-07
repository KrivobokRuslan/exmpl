<?php

namespace app\modules\api\services;

use app\core\models\dto\FileMeta;
use app\core\models\repositories\conditions\file\DownloadFileCondition;
use app\core\models\repositories\conditions\file\SubmittedFilesCondition;
use app\core\models\repositories\conditions\file\UploadFileCondition;
use app\core\models\repositories\interfaces\IFileRepository;
use app\core\models\repositories\interfaces\IProjectRepository;
use app\core\services\interfaces\IFileUploader;
use app\core\models\File;
use app\modules\api\forms\GetFilesLinksForm;
use app\modules\api\forms\SingleGetFileLinkForm;
use app\modules\api\forms\SubmittedFilesForm;
use app\modules\api\forms\UploadFileForm;
use Yii;
use yii\helpers\BaseVarDumper;
use yii\helpers\Json;

class FileService
{
    public function __construct(
        private IProjectRepository $projectRepository,
        private IFileRepository $repository,
        private IFileUploader $uploader
    ) {}

    public function getLinks(GetFilesLinksForm $form): array {
        $result = [];
        $project = $this->projectRepository->getByApiKey($form->apiKey);

        foreach ($form->files as $getFileLinkForm) {
            $file = File::create(
                projectId: $project->id,
                entityName: $form->entityName,
                userUid: $form->userUid,
                action: $form->action,
                fileHash: $getFileLinkForm->fileHash,
                entityUid: $form->entityUid,
                meta: new FileMeta(
                    $getFileLinkForm->fileName,
                    $getFileLinkForm->fileSize,
                    $getFileLinkForm->fileExt,
                    $getFileLinkForm->lastModified
                )
            );

            $this->repository->save($file);
            $result[$file->file_hash] = $_ENV['DOMAIN'] . '/upload?token=' . base64_encode(Json::encode([
                    'ut' => $file->upload_token,
                    'entityName' => $file->entity_name,
                    'userUid' => $file->user_uid,
                    'action' => $file->action,
                    'entityUid' => $file->entity_uid,
                    'time' => $file->meta['lastModifiedDate']
                ]));
        }

        return $result;
    }

    public function getUploadLink(SingleGetFileLinkForm $form): array {
        $project = $this->projectRepository->getByApiKey($form->apiKey);
        $file = File::create(
            projectId: $project->id,
            entityName: $form->entityName,
            userUid: $form->userUid,
            action: $form->action,
            fileHash: $form->fileHash,
            entityUid: $form->entityUid,
            meta: new FileMeta(
                $form->fileName,
                $form->fileSize,
                $form->fileExt,
                $form->lastModified
            )
        );

        $this->repository->save($file);
        $file->refresh();

        return [
            'uid' => $file->uid,
            'link' => $_ENV['DOMAIN'] . '/upload?token=' . base64_encode(Json::encode([
                'ut' => $file->upload_token,
                'entityName' => $file->entity_name,
                'userUid' => $file->user_uid,
                'action' => $file->action,
                'entityUid' => $file->entity_uid,
                'time' => $file->meta->lastModifiedDate
            ]))
        ];
    }

    public function upload(UploadFileForm $form): File
    {
        $project = $this->projectRepository->getByApiKey($form->apiKey);
        $file = $this->repository->getReadyForLoadingOneBy(
            new UploadFileCondition(
                $project->id,
                $form->entityName,
                $form->userUid,
                $form->action,
                $form->token,
                $form->entityUid
            )
        );
        $file->moveToLoading();
        $this->repository->save($file);
        $fileName = $this->uploader->saveAs($form->file, Yii::getAlias('@uploadFolder'));
        $file->file_name = $fileName;
        $file->moveToPreload();
        $this->repository->save($file);

        return $file;
    }

    public function submittedFiles(SubmittedFilesForm $form): array
    {
        $result = [];
        $project = $this->projectRepository->getByApiKey($form->apiKey);
        $files = $this->repository->findBy(new SubmittedFilesCondition($form->ids, $project->id));

        foreach ($files as $file) {
            $file->moverToReadyForCheck();
            $this->repository->save($file);
            $result[] = $file->uid;
        }

        return $result;
    }

    public function downloadAsStream(string $apiKey, string $uid): void
    {
        $project = $this->projectRepository->getByApiKey($apiKey);
        $file = $this->repository->getBy(new DownloadFileCondition($uid, $project->id));
        $handle = fopen(Yii::getAlias('@uploadFolder') . '/' . $file->file_name, 'r');
        $response = Yii::$app->response->sendStreamAsFile($handle, $file->file_name);
        fclose($handle);
        $response->send();
    }
}