<?php
/**
 * Handle inbound emails
 *
 * @author J. Miller (@jmillerdesign)
 */

App::uses('PostmarkAppController', 'Postmark.Controller');
class EmailsController extends PostmarkAppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'Auth'
	);

/**
 * beforeFilter
 *
 * @return void
 */
	public function beforeFilter() {
		$this->Auth->allow('inbound');
		parent::beforeFilter();
	}

/**
 * Handle inbound emails
 *
 * @return void
 */
	public function inbound() {
		$pluginDir = APP . 'Plugin' . DS . 'Postmark' . DS;
		$attachmentsDir = $pluginDir . 'webroot' . DS . 'attachments' . DS;

		// Initialize Autoloader
		require_once $pluginDir . 'Vendor' . DS . 'Inbound' . DS . 'lib' . DS . 'Postmark' . DS . 'Autoloader.php';
		\Postmark\Autoloader::register();

		// Parse inbound email
		$email = new \Postmark\Inbound(file_get_contents('php://input'));

		// Download attachments
		$attachments = array();
		if ($email->HasAttachments()) {
			mkdir($attachmentsDir . $email->MessageID());
			foreach($email->Attachments() as $attachment) {
				$attachments[] = $attachment->Name;
				$attachment->Download($attachmentsDir . $email->MessageID() . DS);
			}
		}

		// Format to recipients into an array
		$to = array();
		foreach ($email->Recipients() as $recipient) {
			$to[] = array('email' => $recipient->Email, 'name' => $recipient->Name);
		}

		// Format cc recipients into an array
		$cc = array();
		foreach ($email->UndisclosedRecipients() as $recipient) {
			$cc[] = array('email' => $recipient->Email, 'name' => $recipient->Name);
		}

		// Dispatch Postmark.inbound event
		$this->getEventManager()->dispatch(new CakeEvent('Postmark.inbound', $this, array(
			'subject'     => $email->Subject(),
			'from'        => array(
				'email'   => $email->FromEmail(),
				'name'    => $email->FromName(),
			),
			'to'          => $to,
			'cc'          => $cc,
			'date'        => date('Y-m-d H:i:s', strtotime($email->Date())),
			'tag'         => $email->Tag(),
			'id'          => $email->MessageID(),
			'text'        => $email->TextBody(),
			'html'        => $email->HtmlBody(),
			'attachments' => $attachments
		)));

		die('OK');
	}

}
