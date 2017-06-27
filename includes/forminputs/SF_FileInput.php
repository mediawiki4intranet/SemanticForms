<?php

class SFFileInput extends SFFormInput
{
	public static function getName()
	{
		return 'file';
	}

	public static function getOtherPropTypesHandled()
	{
		return array('_wpg');
	}

	public static function getOtherPropTypeListsHandled()
	{
		return array('_wpg');
	}

	public static function getDefaultCargoTypes()
	{
		return array(
			'File' => array(),
		);
	}

	public function getHtmlText()
	{
		$html = '';
		$imageTitle = Title::newFromText($this->mCurrentValue, NS_FILE);
		$file = wfLocalFile($imageTitle);
		$subname = str_replace(' ', '_', preg_replace('/^([^[]+)/s', '[\1]', $this->mInputName));
		$html .= '<input type="hidden" name="'.htmlspecialchars($this->mInputName).
			'" value="'.htmlspecialchars($this->mCurrentValue).'" />';
		if ($file && $file->exists())
		{
			$html .= '<div style="padding: 5px 0 10px 0">';
			$viewable = preg_match('#^image/(jpeg|png|gif)$#', $file->getMimeType());
			$imageName = htmlspecialchars($file->getName());
			if ($file->getHandler() && ($thumb = $file->createThumb(200, 200)))
				$text = '<img style="vertical-align: top" src="'.$thumb.'" alt="'.$imageName.'" title="'.$imageName.'" />';
			else
				$text = $imageName;
			$html .= '<a href="'.$file->getUrl().'"'.($viewable ? ' class="sfFancyBox"' : '').' target="_blank">'.$text.'</a> ';
			$html .= '(<a href="'.$file->getTitle()->getLocalUrl().'" target="_blank">'.wfMsg('imagepage').'</a>) &nbsp; ';
			$html .= '<label><input type="checkbox" name="_clearfile'.htmlspecialchars($subname).'" /> '.wfMsg('delete').'</label></div>';
		}
		$html .= '<input type="file" name="_newfile'.htmlspecialchars($subname).'" />';
		return $html;
	}

	protected static function setPathVal($key, $value)
	{
		global $wgRequest;
		$key = (array)$key;
		if (count($key) == 1)
		{
			$wgRequest->setVal($key[0], $value);
			return;
		}
		$root = $wgRequest->getArray($key[0]);
		$cur = &$root;
		for ($i = 1; $i < count($key); $i++)
			$cur = &$cur[$key[$i]];
		$cur = $value;
		$wgRequest->setVal($key[0], $root);
	}

	protected static function handleUpload($field, $fileInfo)
	{
		global $wgUser, $wgRequest;
		$upload = UploadFromSF::newFromUpload($fileInfo);
        Hooks::run( 'sfAddFilePrefix', array($wgRequest, &$upload, &$fileInfo));
        // Upload verification
		$details = $upload->verifyUpload();
		if ($details['status'] != UploadBase::OK)
		{
			//self::processVerificationError($details);
			return;
		}
		// Verify permissions for this title
		$permErrors = $upload->verifyTitlePermissions($wgUser);
		if ($permErrors !== true)
		{
			$code = array_shift($permErrors[0]);
			//self::showRecoverableUploadError(self::msg($code, $permErrors[0])->parse());
			return;
		}
		// Check if the file already exists
		$file = $upload->getLocalFile();
		if ($file->exists())
		{
			if ($file->getSha1() == $upload->getTempFileSha1Base36())
			{
				self::setPathVal($field, $file->getTitle()->getPrefixedText());
				return;
			}
			else
			{
                $i = 1;
				do
				{
					$name = preg_replace_callback('/(^|[^\.])(\.[^\.]*)?$/s', function($m) use($i) { return $m[1]."_".$i.$m[2]; }, $fileInfo['name']);
                    $upload = UploadFromSF::newFromUpload($fileInfo, $name);
					$file = $upload->getLocalFile();
					$i++;
				} while ($file->exists());
			}
		}
		$status = $upload->performUpload('-', '', false, $wgUser);
		self::setPathVal($field, $file->getTitle()->getPrefixedText());
	}

	protected static function handleRecurseUploads($name, $array)
	{
		if (!is_array($array['tmp_name']))
			self::handleUpload($name, $array);
		else
		{
			foreach ($array['tmp_name'] as $key => $sub)
			{
				$file = array();
				foreach ($array as $k => $vs)
					$file[$k] = isset($vs[$key]) ? $vs[$key] : NULL;
				self::handleRecurseUploads(array_merge($name, array($key)), $file);
			}
		}
	}

	protected static function clearUploads($name, $array)
	{
		foreach ($array as $k => $v)
		{
			$path = array_merge($name, array($k));
			if (is_array($v))
				self::clearUploads($path, $v);
			elseif ($v)
				self::setPathVal($path, '');
		}
	}

	public static function handleUploads()
	{
		global $wgRequest;
		$clear = $wgRequest->getArray('_clearfile');
		if ($clear)
			self::clearUploads(array(), $clear);
		if (isset($_FILES['_newfile']))
			self::handleRecurseUploads(array(), $_FILES['_newfile']);
	}
}

class WebRequestNestedUpload extends WebRequestUpload
{
	protected $fileInfo;

	public function __construct( $request, $info )
	{
		$this->request = $request;
		$this->fileInfo = $info;
	}

	public function exists()
	{
		return ( true && $this->fileInfo );
	}
}

class UploadFromSF extends UploadFromFile
{
	protected $mUpload = null;

	function initializeFromRequest( &$request )
	{
	}

	static function newFromUpload( $upload, $desiredDestName = NULL )
	{
		global $wgRequest;
		$upload = new WebRequestNestedUpload( $wgRequest, $upload );
		if ( !$desiredDestName )
			$desiredDestName = $upload->getName();
		$self = new self();
		$self->initialize( $desiredDestName, $upload );
		return $self;
	}
}
