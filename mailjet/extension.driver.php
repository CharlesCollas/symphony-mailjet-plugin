<?php

	/**
	 * Extension driver
	 *
	 * @package Mailjet extension
	 * @author Mailjet
	 */
	Class extension_mailjet extends Extension
	{
		public function about()
		{
			return array(
				'name'			=> 'Mailjet',
				'version'		=> '1',
				'release-date'	=> '2013-03-01',
				'author'		=> array(
					array(
						'name' => 'Mailjet Team',
						'website' => 'http://www.mailjet.com/',
						'email' => 'plugins@mailjet.com'
					)
				)
			);
		}

		/**
		 * Function to be executed on uninstallation
		 */
		public function uninstall()
		{
			Symphony::Configuration()->remove('mailjet');
			Administration::instance()->saveConfig();

			return true;
		}
	}
