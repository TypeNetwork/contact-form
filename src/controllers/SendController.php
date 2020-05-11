<?php

namespace craft\contactform\controllers;

use Craft;
use craft\contactform\models\Submission;
use craft\contactform\Plugin;
use craft\web\Controller;
use craft\web\UploadedFile;
use yii\web\Response;

class SendController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * Sends a contact form submission.
     *
     * @return Response|null
     */
    public function actionIndex()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        $submission = new Submission();
        $submission->fromEmail = $request->getBodyParam('fromEmail');
        $submission->fromName = $request->getBodyParam('fromName');
        $submission->subject = $request->getBodyParam('subject') ?: "Contact form";

        $message = $request->getBodyParam('message');
        if (is_array($message)) {
            $submission->message = array_filter($message, function($value) {
                return $value !== '';
            });
        } else {
            $submission->message = $message;
        }

        if ($settings->allowAttachments && isset($_FILES['attachment']) && isset($_FILES['attachment']['name'])) {
            if (is_array($_FILES['attachment']['name'])) {
                $submission->attachment = UploadedFile::getInstancesByName('attachment');
            } else {
                $submission->attachment = [UploadedFile::getInstanceByName('attachment')];
            }
        }

		$formatted = array();
		if (is_array($message)) {
			foreach ($message as $k => $v) {
				if ($k !== "body") {
					$formatted[] = "{$k}: $v";
				}
			}
			if (isset($message['body'])) {
				$formatted[] = "";
				$formatted[] = $message['body'];
			}
			
			$formatted = implode("\r\n", $formatted);
		} else {
			$formatted = $message;
		}
		
		$formatted = preg_replace('/(?<!\r)\n/', "\r\n", $formatted);
		
//        if (!$plugin->getMailer()->send($submission)) {
		if (!mail($settings->toEmail, ($settings->prependSubject ? $settings->prependSubject . " " : "") . $submission->subject, $formatted, "From: custom@typenetwork.com\r\nReply-to: \"{$submission->fromName}\" <{$submission->fromEmail}>")) {
            if ($request->getAcceptsJson()) {
//                 return $this->asJson(['errors' => $submission->getErrors()]);
                 return $this->asJson(['errors' => ["Message failed to send! Please email custom@typenetwor.com directly. We aplogize for this error."]]);
            }

/*
            Craft::$app->getSession()->setError(Craft::t('contact-form', 'There was a problem with your submission, please check the form and try again!'));
            Craft::$app->getUrlManager()->setRouteParams([
                'variables' => ['message' => $submission]
            ]);
*/

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice($settings->successFlashMessage);
        return $this->redirectToPostedUrl($submission);
    }
}