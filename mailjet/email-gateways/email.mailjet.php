<?php

	require_once(TOOLKIT . '/class.emailgateway.php');
	require_once(TOOLKIT . '/class.emailhelper.php');
	require_once(TOOLKIT . '/class.smtp.php');

	class MailjetGateway extends EmailGateway
	{
		protected $_SMTP;
		protected $_host = 'in.mailjet.com';
		protected $_port = 587;
		protected $_secure = 'no';
		protected $_protocol = 'tcp';

		public function __construct()
		{
			parent::__construct();

			$secure = Symphony::Configuration()->get('secure', 'mailjet');

			if ($secure == 'tls')
			{
				$this->_protocol = 'tcp';
				$this->_secure = 'tls';
				$this->_port = 465;
			}
			else if ($secure == 'ssl')
			{
				$this->_protocol = 'ssl';
				$this->_secure = 'ssl';
				$this->_port = 465;
			}
			else
			{
				$this->_protocol = 'tcp';
				$this->_secure = 'no';
				$this->_port = 587;
			}
		}

		public function about()
		{
			return array('name' => 'Mailjet');
		}

		/**
		 * Send an email using Mailjet's SMTP server
		 *
		 * @return boolean
		 */
		public function send()
		{
		// $this->validate();

			$settings = array();
			$settings['username'] = Symphony::Configuration()->get('api_key', 'mailjet');
			$settings['password'] = Symphony::Configuration()->get('api_secret', 'mailjet');
			$settings['secure'] = $this->_secure;

			try
			{
				if (!is_a($this->_SMTP, 'SMTP'))
					$this->_SMTP = new SMTP($this->_host, $this->_port, $settings);

				// Encode recipient names (but not any numeric array indexes)
				foreach ($this->_recipients as $name => $email)
				{
					$name = empty($name) ? $name : EmailHelper::qEncode($name);
					$recipients[$name] = $email;
				}

				// Combine keys and values into a recipient list (name <email>, name <email>).
				$recipient_list = EmailHelper::arrayToList($recipients);

				// Encode the subject
				$subject = EmailHelper::qEncode((string)$this->_subject);

				$from_email = Symphony::Configuration()->get('from_address', 'mailjet');
				$this->setSenderEmailAddress($from_email);

				$from_name = Symphony::Configuration()->get('from_name', 'mailjet');
				$this->setSenderName($from_name);

				// build from address
				if (empty($from_name))
					$from = $from_email;
				else
					$from = $from_name . ' <' . $from_email . '>';

				// Build the 'Reply-To' header field body
				if (!empty($this->_reply_to_email_address))
				{
					$reply_to = empty($this->_reply_to_name)
					            ? $this->_reply_to_email_address
					            : EmailHelper::qEncode($this->_reply_to_name) . ' <' . $this->_reply_to_email_address . '>';
				}

				if (!empty($reply_to))
					$this->_header_fields = array_merge($this->_header_fields, array('Reply-To' => $reply_to));

				// Build the body text using attachments, html-text and plain-text.
				$this->prepareMessageBody();

				// Build the header fields
				$this->_header_fields = array_merge(
					$this->_header_fields,
					array(
						'Message-ID'   => sprintf('<%s@%s>', md5(uniqid()) , HTTP_HOST),
						'Date'         => date('r'),
						'From'         => $from,
						'Subject'      => $subject,
						'To'           => $recipient_list,
						'X-Mailer'     => 'Mailjet Symphony Module',
						'MIME-Version' => '1.0'
					)
				);

				// Set header fields and fold header field bodies
				foreach ($this->_header_fields as $name => $body)
					$this->_SMTP->setHeader($name, EmailHelper::fold($body));

				// Send the email command. If the envelope from variable is set, use that for the MAIL command. This improves bounce handling.
				$this->_SMTP->sendMail($from, $recipient_list, $this->_subject, $this->_body);

				if ($this->_keepalive == false)
					$this->closeConnection();

				$this->reset();
			}
			catch (SMTPException $e)
			{
				throw new EmailGatewayException($e->getMessage());
			}

			return true;
		}

		/**
		 * Resets the headers, body, subject
		 *
		 * @return void
		 */
		public function reset()
		{
			$this->_header_fields = array();;
			$this->_recipients =	array();
			$this->_subject =		null;
			$this->_body =			null;
		}

		public function closeConnection()
		{
			if (is_a($this->_SMTP, 'SMTP'))
			{
				try
				{
					$this->_SMTP->quit();
					return parent::closeConnection();
				}
				catch(Exception $e) { }
			}

			parent::closeConnection();
			return false;
		}

		/**
		 * The preferences to add to the preferences pane in the admin area.
		 *
		 * @return XMLElement
		 */
		public function getPreferencesPane()
		{
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings pickable');
			$group->setAttribute('id', 'mailjet');
			$group->appendChild(new XMLElement('legend', __('Mailjet')));

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			$label = Widget::Label(__('From Name'));
			$label->appendChild(Widget::Input('settings[mailjet][from_name]', Symphony::Configuration()->get('from_name', 'mailjet')));
			$div->appendChild($label);
			
			$label = Widget::Label(__('From Address'));
			$label->appendChild(Widget::Input('settings[mailjet][from_address]', Symphony::Configuration()->get('from_address', 'mailjet')));
			$div->appendChild($label);
			$group->appendChild($div);

			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			$label = Widget::Label(__('API Key'));
			$label->appendChild(Widget::Input('settings[mailjet][api_key]', Symphony::Configuration()->get('api_key', 'mailjet')));
			$div->appendChild($label);

			$label = Widget::Label(__('API Secret'));
			$label->appendChild(Widget::Input('settings[mailjet][api_secret]', Symphony::Configuration()->get('api_secret', 'mailjet')));
			$div->appendChild($label);
			$group->appendChild($div);

			$label = Widget::Label();
			$label->setAttribute('class', 'column');

			$options = array(
				array('no', $this->_secure == 'no', __('No encryption')),
				array('ssl', $this->_secure == 'ssl', __('SSL encryption')),
				array('tls', $this->_secure == 'tls', __('TLS encryption')),
			);
			$select = Widget::Select('settings[mailjet][secure]', $options);
			$label->appendChild($select);
			$group->appendChild($label);

			return $group;
		}
	}
?>