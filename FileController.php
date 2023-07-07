<?php

namespace app\modules\api\controllers;

use app\core\models\dto\FileUploadPayload;
use app\modules\api\forms\GetFilesLinksForm;
use app\modules\api\forms\SingleGetFileLinkForm;
use app\modules\api\forms\SubmittedFilesForm;
use app\modules\api\forms\UploadFileForm;
use app\modules\api\services\FileService;
use DomainException;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use OpenApi\Attributes as OA;

class FileController extends BaseController
{
    private FileService $service;

    public function __construct($id, $module, FileService $service, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->service = $service;
    }

    #[OA\Post(
        path: "/get-file-links",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/GetFileLinksRequestBody'
            )
        ),
        tags: ["File API"],
        parameters: [
            new OA\Parameter(
                name: 'X-Storage-Key',
                description: 'Access Token',
                in: 'header',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'token',
                ),
            )
        ],
        responses: [
            new OA\Response(
                response: 400,
                description: 'Bad Request',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BadRequestResponse'
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthorizedResponse'
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation Error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorsResponse'
                ),
            ),
            new OA\Response(
                response: 200,
                description: 'Success response',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/GetFileLinksResponse'
                ),
            )
        ]
    )]
    public function actionGetLinks(): array
    {
        $form = new GetFilesLinksForm();
        $form->setFilesForms(ArrayHelper::getValue(Yii::$app->request->getBodyParams(), 'files', []));

        if ($form->load(Yii::$app->request->getBodyParams(), '') && $form->validate()) {
            try {
                return $this->_200('File links', ['links' => $this->service->getLinks($form)]);
            } catch (DomainException $e) {
                return $this->_400($e->getMessage());
            }
        }

        return $this->_422($form->getErrors());
    }

    #[OA\Post(
        path: "/get-upload-link",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/GetFileUploadLinkRequestBody'
            )
        ),
        tags: ["File API"],
        parameters: [
            new OA\Parameter(
                name: 'X-Storage-Key',
                description: 'Access Token',
                in: 'header',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'token',
                ),
            )
        ],
        responses: [
            new OA\Response(
                response: 400,
                description: 'Bad Request',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BadRequestResponse'
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthorizedResponse'
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation Error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorsResponse'
                ),
            ),
            new OA\Response(
                response: 200,
                description: 'Success response',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/GetFileLinksResponse'
                ),
            )
        ]
    )]
    public function actionGetUploadLink(): array
    {
        $form = new SingleGetFileLinkForm();

        if ($form->load(Yii::$app->request->getBodyParams(), '') && $form->validate()) {
            try {
                return $this->_200('File links', ['file' => $this->service->getUploadLink($form)]);
            } catch (DomainException $e) {
                return $this->_400($e->getMessage());
            }
        }

        return $this->_422($form->getErrors());
    }

    #[OA\Post(
        path: "/upload",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/UploadFileRequestBody'
            )
        ),
        tags: ["File API"],
        parameters: [
            new OA\Parameter(
                name: 'X-Storage-Key',
                description: 'Access Token',
                in: 'header',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'token',
                ),
            ),
            new OA\Parameter(
                name: 'token',
                description: 'One Time Token',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'token',
                ),
            )
        ],
        responses: [
            new OA\Response(
                response: 400,
                description: 'Bad Request',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BadRequestResponse'
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthorizedResponse'
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation Error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorsResponse'
                ),
            ),
            new OA\Response(
                response: 200,
                description: 'Success response',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UploadFileResponse'
                ),
            )
        ]
    )]
    public function actionUpload(string $token): array
    {
        $payload = new FileUploadPayload(...Json::decode(base64_decode($token)));
        $form = new UploadFileForm($payload);

        if ($form->load(Yii::$app->request->getBodyParams(), '') && $form->validate()) {
            try {
                $file = $this->service->upload($form);
                return $this->_200('Uploaded', [
                    'uid' => $file->uid,
                    'hash' => $form->getHash()
                ]);
            } catch (DomainException $e) {
                return $this->_400($e->getMessage());
            }
        }

        return $this->_422($form->getErrors());
    }

    #[OA\Put(
        path: "/submitted",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/SubmittedFilesRequestBody'
            )
        ),
        tags: ["File API"],
        parameters: [
            new OA\Parameter(
                name: 'X-Storage-Key',
                description: 'Access Token',
                in: 'header',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    default: 'token',
                ),
            ),
        ],
        responses: [
            new OA\Response(
                response: 400,
                description: 'Bad Request',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/BadRequestResponse'
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthorizedResponse'
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation Error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorsResponse'
                ),
            ),
            new OA\Response(
                response: 200,
                description: 'Success response',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubmittedFilesResponse'
                ),
            )
        ]
    )]
    public function actionSubmittedFiles(): array
    {
        $form = new SubmittedFilesForm();

        if ($form->load(Yii::$app->request->getBodyParams(), '') && $form->validate()) {
            try {
                return $this->_200('Submitted', ['uids' => $this->service->submittedFiles($form)]);
            } catch (DomainException $e) {
                return $this->_400($e->getMessage());
            }
        }

        return $this->_422($form->getErrors());
    }

    public function actionDownloadAsStream(string $uid)
    {
        try {
            $this->service->downloadAsStream(Yii::$app->request->getBodyParam('apiKey', ''), $uid);
        } catch (DomainException $e) {
            return $this->_400($e->getMessage());
        }
    }
}