<?php
/**
 * Webkul Software.
 *
 * @category  Webkul
 * @package   Webkul_ImageGallery
 * @author    Webkul
 * @copyright Webkul Software Private Limited (https://webkul.com)
 * @license   https://store.webkul.com/license.html
 */
namespace Webkul\ImageGallery\Controller\Adminhtml\Images;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Backend\App\Action;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Filesystem $fileSystem
     */
    protected $_fileSystem;

    /**
     * @var \Magento\MediaStorage\Model\File\UploaderFactory
     */
    protected $_uploader;

    /**
     * @var \Webkul\ImageGallery\Model\ImagesFactory
     */
    protected $_images;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $_fileDriver;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    public $file;

    /**
     * @param Action\Context $context
     * @param \Magento\Framework\Filesystem $fileSystem
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $fileUploader
     * @param \Webkul\ImageGallery\Model\ImagesFactory $images
     * @param \Magento\Framework\Filesystem\Driver\File $fileDriver
     * @param \Magento\Framework\Filesystem\Io\File $file
     */
    public function __construct(
        Action\Context $context,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploader,
        \Webkul\ImageGallery\Model\ImagesFactory $images,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        \Magento\Framework\Filesystem\Io\File $file
    ) {
        $this->_fileSystem = $fileSystem;
        $this->_uploader = $fileUploader;
        $this->_images = $images;
        $this->_fileDriver = $fileDriver;
        $this->file = $file;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Webkul_ImageGallery::images');
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $error = false;
        $data = $this->getRequest()->getParams();
        if ($this->getRequest()->isPost()) {
            $id = (int) $this->getRequest()->getParam('id');
            $path = $this->_fileSystem
                        ->getDirectoryRead(DirectoryList::MEDIA)
                        ->getAbsolutePath('imagegallery/images/');
            $this->_fileDriver->createDirectory($path, 0755);
            try {
                $allowedExtensions = ['jpg', 'jpeg', 'gif', 'png'];
                $uploader = $this->_uploader->create(['fileId' => 'image']);
                $uploader->setAllowedExtensions($allowedExtensions);
                $imageData = $uploader->validateFile();
                $isValidImage = $this->validateImage($imageData['tmp_name']);
                if (!$isValidImage) {
                    $this->messageManager->addError(__("Invalid image type"));
                    return $resultRedirect->setPath('*/*/');
                }
                $name = $imageData['name'];
                $ext = substr($name, strrpos($name, '.') + 1);
                $imageName = 'File-'.time().'.'.$ext;
                $uploader->setAllowRenameFiles(true);
                $uploader->setFilesDispersion(false);
                $uploader->save($path, $imageName);
                $data['image'] = 'imagegallery/images/'.$imageName;
            } catch (\Exception $e) {
                $error = true;
            }
            $data1 = [];
            foreach ($data['image'] as $imageName1) {
                if (!empty($imageName1['name'])) {
                    $name = $imageName1['name'];
                    $data1 = 'imagegallery/images/'.$name;
                } else {
                    $url = $imageName1['url'];
                    $basename = $this->file->getPathInfo($url);
                    $name = $basename['basename'];
                    $data1 = 'imagegallery/images/'.$name;
                }
            }
            $data['image'] =  $data1;
            $this->setImageData($data);
            $this->messageManager->addSuccess(__('Image saved succesfully'));
        } else {
            $this->messageManager->addError(__("Something went wrong"));
        }
        return $resultRedirect->setPath('*/*/');
    }
    /**
     * Validate Image
     *
     * @param string $imagePath
     *
     * @return bool
     */
    public function validateImage($imagePath)
    {
        $imageDetails = false;
        try {
            $fileContents = $this->_fileDriver->fileGetContents($imagePath);
            $imageDetails = getimagesizefromstring($fileContents);
            if ($imageDetails) {
                return true;
            }
        } catch (\Exception $e) {
            return $imageDetails;
        }
        return $imageDetails;
    }

    /**
     * Save Image Data
     *
     * @param array $data
     */
    public function setImageData($data)
    {
        $time = date('Y-m-d H:i:s');
        $model = $this->_images->create();
        $data['updated_time'] = $time;
        if (array_key_exists("id", $data)) {
            $model->addData($data)->setId($data['id'])->save();
        } else {
            $data['created_time'] = $time;
            $model->setData($data)->save();
        }
    }
}
