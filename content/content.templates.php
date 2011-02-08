<?php

require_once(TOOLKIT . '/class.administrationpage.php');
require_once(EXTENSIONS . '/email_templates/lib/class.emailtemplate.php');
require_once(EXTENSIONS . '/email_templates/lib/class.emailtemplatemanager.php');

Class contentExtensionemail_templatestemplates extends AdministrationPage {
	
	function __construct(){
		parent::__construct(Symphony::Engine());
		$this->_uri = URL . '/symphony/extension/email_templates';
	}
	
	function __viewIndex(){
		$this->setPageType('table');
		$this->setTitle(__('Symphony &ndash; Email Templates'));
		$this->appendSubheading(__('Email Templates'), Widget::Anchor(
			'Create New', "{$this->_uri}/templates/new/",
			'Create a new email template', 'create button'
		));
		
		$tableHead = array(
			array('Name', 'col'),
			array('Template', 'col'),
			array('Preview', 'col')
		);
		
		$templates = EmailTemplateManager::listAll();
		
		if(empty($templates)){
			$tableBody = array(
				Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
			);
		}
		else{
			$i = 0;
			foreach($templates as $template){
				$col_name = Widget::TableData(
					Widget::Anchor(
						$template->getName(),
						"{$this->_uri}/templates/edit/{$template->getHandle()}/"
					)
				);
				$col_name->appendChild(Widget::Input("items[" . ++$i . "]", null, 'checkbox'));
				
				$tmp = null;
				$prv = null;
				$config = $template->getConfig();
				if(is_array($config['templates'])){
					foreach($config['templates'] as $name=>$tmpl){
						$tmp[] = Widget::Anchor(ucfirst(strtolower($name)), "{$this->_uri}/templates/edit/{$template->getHandle()}/{$name}")->generate();
						$prv[] = Widget::Anchor(ucfirst(strtolower($name)), "{$this->_uri}/templates/preview/{$template->getHandle()}/{$name}/")->generate();
					}
					$col_template = Widget::TableData(implode(', ',$tmp));
					$col_preview = Widget::TableData(implode(', ',$prv));
				}
				else{
					$col_template = Widget::TableData(__('None Found'), 'inactive');
					$col_preview = Widget::TableData(__('None Found'), 'inactive');
				}
				
				$tableBody[] = Widget::TableRow(array($col_name, $col_template, $col_preview), null);
			}
		}
		
		$table = Widget::Table(
			Widget::TableHead($tableHead), null,
			Widget::TableBody($tableBody), 
			'orderable, selectable'
		);
		
		$this->Form->appendChild($table);
		
		$actions = new XMLElement('div');
		$actions->setAttribute('class', 'actions');

		$options = array(
			array(null, false, 'With Selected...'),
			array('delete', false, 'Delete')
		);

		$actions->appendChild(Widget::Select('with-selected', $options));
		$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));

		$this->Form->appendChild($actions); 
	}
	
	function __viewEdit(){
		if($this->_context[3] == 'saved' || $this->_context[2] == 'saved') {
			$this->pageAlert(
				__(
					'Template updated at %1$s.', 
					array(
						DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__), 
					)
				),
				Alert::SUCCESS
			);
		}
		list(,$handle, $template) = $this->_context;
		if(!is_null($template)){
			$this->_editXSL();
		}
		else{
			$this->_editClass();
		}
	}
	
	public function __actionEdit() {
		list(,$handle, $template) = $this->_context;
		
		//Edit Template xsl Files
		if(!is_null($template)){
			$filename = 'template.' . $template . '.xsl';
			$folder_name = $handle;
			$file_abs = dirname(__FILE__) . '/../templates/' . basename($folder_name) . '/' . basename($filename);
			$fields = $_POST['fields'];
			$this->_errors = array();

			if(!isset($fields['body']) || trim($fields['body']) == '') {
				$this->_errors['body'] = __('Body is a required field.');

			} elseif(!General::validateXML($fields['body'], $errors, false, new XSLTProcess())) {
				$this->_errors['body'] = __('This document is not well formed. The following error was returned: <code>%s</code>', array($errors[0]['message']));
			}

			if(empty($this->_errors)) {
				if(!$write = General::writeFile($file_abs, $fields['body'], Symphony::Configuration()->get('write_mode', 'file'))) {
					$this->pageAlert(__('Template could not be written to disk. Please check permissions on <code>/extensions/email_templates/templates/</code>.'), Alert::ERROR);

				} else {
					redirect($this->_uri . '/templates/edit/' . $handle . '/' . $template  . '/saved/');
				}
			}
		}
		
		//Edit Template Configuration Files
		else{
		}
	}
	
	public function __viewPreview(){
		list(,$handle, $template) = $this->_context;
		$templates = EmailTemplateManager::load($handle);
		echo $templates->preview($template);
		die();
	}
		
	protected function _editXSL(){
		list(,$handle, $template) = $this->_context;
		if(!empty($handle)){
			$this->setPageType('form');
			$this->Form->setAttribute('action', $this->_uri . '/templates/edit/' . $handle . '/' . $template . '/');
			$this->setTitle(__('Symphony &ndash; Email Templates &ndash; Edit'));
			try{
				$tmpl = EmailTemplateManager::load($handle);
				
				$config = $tmpl->getConfig();
				if(!is_null($config['templates'][$template])){
					
					$file_abs = dirname(__FILE__) . '/../templates/' . $handle . '/' . basename($config['templates'][$template]);
					
					if(!is_file($file_abs)) redirect($this->_uri . '/templates/');
					$fields['body'] = @file_get_contents($file_abs);
					
					$this->appendSubheading(__($config['templates'][$template] ? $config['templates'][$template] : __('Untitled')), Widget::Anchor(__('Edit Configuration'), $this->_uri . '/templates/edit/' . $handle, __('Edit Template Confguration'), 'button', NULL, array('accesskey' => 't')));
					
					if(!empty($_POST)) $fields = $_POST['fields'];

					$fields['body'] = General::sanitize($fields['body']);

					$fieldset = new XMLElement('fieldset');
					$fieldset->setAttribute('class', 'primary');

					$label = Widget::Label(__('Body'));
					$label->appendChild(Widget::Textarea(
						'fields[body]', 30, 80, $fields['body'],
						array(
							'class'	=> 'code'
						)
					));

					if(isset($this->_errors['body'])) {
						$label = $this->wrapFormElementWithError($label, $this->_errors['body']);
					}
					
					$utilities = General::listStructure(UTILITIES, array('xsl'), false, 'asc', UTILITIES);
					$utilities = $utilities['filelist'];

					if(is_array($utilities) && !empty($utilities)) {
						$div = new XMLElement('div');
						$div->setAttribute('class', 'secondary');

						$p = new XMLElement('p', __('Utilities'));
						$p->setAttribute('class', 'label');
						$div->appendChild($p);

						$ul = new XMLElement('ul');
						$ul->setAttribute('id', 'utilities');

						foreach ($utilities as $index => $util) {
							$li = new XMLElement('li');

							if($index % 2 != 1) $li->setAttribute('class', 'odd');

							$li->appendChild(Widget::Anchor($util, SYMPHONY_URL . '/blueprints/utilities/edit/' . str_replace('.xsl', '', $util) . '/', NULL));
							$ul->appendChild($li);
						}

						$div->appendChild($ul);
						$this->Form->appendChild($div);
					}

					$fieldset->appendChild($label);
					$this->Form->appendChild($fieldset);
					$div = new XMLElement('div');
					$div->setAttribute('class', 'actions');
					$div->appendChild(Widget::Input(
						'action[save_t]', __('Save Changes'),
						'submit', array('accesskey' => 's')
					));

					$this->Form->appendChild($div);
				}
				else{
					throw new FrontendPageNotFoundException();
				}				
			}
			catch(EmailTemplateManagerException $e){
				throw new FrontendPageNotFoundException();
			}
		}
		else{
			throw new FrontendPageNotFoundException();
		}
	}
	
	protected function _editClass(){
		list(,$handle, $template) = $this->_context;
		if(!empty($handle)){
			$this->setPageType('table');
			$this->setTitle(__('Symphony &ndash; Email Templates &ndash; Edit'));
			try{
				$templates = EmailTemplateManager::load($handle);
			}
			catch(EmailTemplateManagerException $e){
				// TODO: log error?
				throw new FrontendPageNotFoundException();
			}
		}
		else{
			throw new FrontendPageNotFoundException();
		}
	}
	
	function __getclassName(){
		return 'contentExtensionemail_templateindex'; 
	}
}