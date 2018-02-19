<?php

namespace App\Admin\User;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;

use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class UserAdmin extends AbstractAdmin {
	private $action;
	
	protected $datagridValues = array(
		// display the first page (default = 1)
//        '_page' => 1,
		// reverse order (default = 'ASC')
		'_sort_order' => 'DESC',
		// name of the ordered field (default = the model's id field, if any)
		'_sort_by'    => 'updatedAt',
	);
	
	/**
	 * @param string $name
	 * @param User   $object
	 */
	public function isGranted($name, $object = null) {
		$container = $this->getConfigurationPool()->getContainer();
		$isAdmin   = $container->get('security.authorization_checker')->isGranted('ROLE_ADMIN');
//        $pos = $container->get('app.user')->getPosition();
		if(in_array($name, [ 'CREATE', 'DELETE', 'LIST' ])) {
			return $isAdmin;
		}
		if($name === 'EDIT') {
			if($isAdmin) {
				return true;
			}
			if( ! empty($object) && $object->getId() === $container->get('app.user')->getUser()->getId()) {
				return true;
			}
			
			return false;
		}
//        if (empty($isAdmin)) {
//            return false;
//        }
		
		return parent::isGranted($name, $object);
	}
	
	public function toString($object) {
		return $object instanceof User
			? $object->getEmail()
			: 'Talent'; // shown in the breadcrumb on the create view
	}
	
	public function createQuery($context = 'list') {
		/** @var ProxyQueryInterface $query */
		$query = parent::createQuery($context);
		if(empty($this->getParentFieldDescription())) {
//            $this->filterQueryByPosition($query, 'position', '', '');
		}

//        $query->andWhere()
		
		return $query;
	}
	
	public function configureRoutes(RouteCollection $collection) {
		parent::configureRoutes($collection);
		$collection->add('talent_bank', 'talent-bank');
		$collection->add('show_user_profile', $this->getRouterIdParameter() . '/show-user-profile');
		
	}
	
	public function getTemplate($name) {
		return parent::getTemplate($name);
	}
	
	protected function configureShowFields(ShowMapper $showMapper) {
		$this->configureParentShowFields($showMapper);
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function configureListFields(ListMapper $listMapper) {
		$listMapper
			->addIdentifier('username')
			->add('email')
			->add('groups')
			->add('enabled', null, [ 'editable' => true ])
			->add('locked', null, [ 'editable' => true ])
			->add('createdAt');
		
		if($this->isGranted('ROLE_ALLOWED_TO_SWITCH')) {
			$listMapper
				->add('impersonating', 'string', [ 'template' => 'SonataUserBundle:Admin:Field/impersonating.html.twig' ]);
		}
		
		$listMapper->remove('impersonating');
		$listMapper->remove('groups');
//		$listMapper->add('positions', null, [ 'template' => '::admin/user/list__field_positions.html.twig' ]);
	}
	
	private function configureParentFormFields(FormMapper $formMapper) {
		
		// define group zoning
		$formMapper
			->tab('User')
			->with('Profile', [ 'class' => 'col-md-6' ])->end()
			->with('General', [ 'class' => 'col-md-6' ])->end()
			->with('Social', [ 'class' => 'col-md-6' ])->end()
			->end()
			->tab('Security')
			->with('Status', [ 'class' => 'col-md-4' ])->end()
			->with('Groups', [ 'class' => 'col-md-4' ])->end()
			->with('Keys', [ 'class' => 'col-md-4' ])->end()
			->with('Roles', [ 'class' => 'col-md-12' ])->end()
			->end();
		
		$now = new \DateTime();
		
		$formMapper
			->tab('User')
			->with('General')
			->add('username')
			->add('email')
			->add('plainPassword', 'text', [
				'required' => ( ! $this->getSubject() || is_null($this->getSubject()->getId())),
			])
			->end()
			->with('Profile')
			->add('dateOfBirth', 'sonata_type_date_picker', [
				'years'       => range(1900, $now->format('Y')),
				'dp_min_date' => '1-1-1900',
				'dp_max_date' => $now->format('c'),
				'required'    => false,
			])
			->add('firstname', null, [ 'required' => false ])
			->add('lastname', null, [ 'required' => false ])
			->add('website', 'url', [ 'required' => false ])
			->add('biography', 'text', [ 'required' => false ])
			->add('gender', 'Sonata\UserBundle\Form\Type\UserGenderListType', [
				'required'           => true,
				'translation_domain' => $this->getTranslationDomain(),
			])
			->add('locale', 'locale', [ 'required' => false ])
			->add('timezone', 'timezone', [ 'required' => false ])
			->add('phone', null, [ 'required' => false ])
			->end()
			->with('Social')
			->add('facebookUid', null, [ 'required' => false ])
			->add('facebookName', null, [ 'required' => false ])
			->add('twitterUid', null, [ 'required' => false ])
			->add('twitterName', null, [ 'required' => false ])
			->add('gplusUid', null, [ 'required' => false ])
			->add('gplusName', null, [ 'required' => false ])
			->end()
			->end();
		
		if($this->getSubject() && ! $this->getSubject()->hasRole('ROLE_SUPER_ADMIN')) {
			$formMapper
				->tab('Security')
				->with('Status')
				->add('locked', null, [ 'required' => false ])
				->add('expired', null, [ 'required' => false ])
				->add('enabled', null, [ 'required' => false ])
				->add('credentialsExpired', null, [ 'required' => false ])
				->end()
				->with('Groups')
				->add('groups', 'sonata_type_model', [
					'required' => false,
					'expanded' => true,
					'multiple' => true,
				])
				->end()
				->with('Roles')
				->add('realRoles', 'Sonata\UserBundle\Form\Type\SecurityRolesType', [
					'label'    => 'form.label_roles',
					'expanded' => true,
					'multiple' => true,
					'required' => false,
				])
				->end()
				->end();
		}
		
		$formMapper
			->tab('Security')
			->with('Keys')
			->add('token', null, [ 'required' => false ])
			->add('twoStepVerificationCode', null, [ 'required' => false ])
			->end()
			->end();
	}
	
	protected function configureFormFields(FormMapper $formMapper) {
		if($this->getConfigurationPool()->getContainer()->get('security.authorization_checker')->isGranted('ROLE_SUPER_ADMIN')) {
			$this->configureParentFormFields($formMapper);
		} else {
//        $formMapper->removeGroup('Social','User');
//        $formMapper->removeGroup('Groups','Security');
//        $formMapper->removeGroup('Keys','Security');
//        $formMapper->removeGroup('Status','Security');
//        $formMapper->removeGroup('Roles','Security');
//        $formMapper->remove('Security');
//
//        $formMapper->remove('dateOfBirth');
//        $formMapper->remove('website');
//        $formMapper->remove('biography');
//        $formMapper->remove('gender');
//        $formMapper->remove('locale');
//        $formMapper->remove('timezone');
//        $formMapper->remove('phone');
			$formMapper
				->with('Profile', [ 'class' => 'col-md-6' ])->end()
				->with('General', [ 'class' => 'col-md-6' ])->end();
			
			$formMapper
				->with('General')
//                ->add('username')
				->add('email')
//                ->add('admin')
				->add('plainPassword', 'text', [
					'required' => ( ! $this->getSubject() || is_null($this->getSubject()->getId())),
				])
				->end()
				->with('Profile');
			
			if( ! empty($this->getConfigurationPool()->getContainer()->get('app.user')->getUser()->getThanhVien())) {
				$formMapper
					->add('thanhVien.lastname', null, [
						'required'           => false,
						'label'              => 'thanh_vien.label_lastname',
						'translation_domain' => 'BinhLeAdmin'
					])
					->add('thanhVien.middlename', null, [
						'required'           => false,
						'label'              => 'thanh_vien.label_middlename',
						'translation_domain' => 'BinhLeAdmin'
					])
					->add('thanhVien.firstname', null, [
						'required'           => false,
						'label'              => 'thanh_vien.label_firstname',
						'translation_domain' => 'BinhLeAdmin'
					]);
				$formMapper->add('thanhVien.soDienThoai', null, array(
					'label'              => 'thanh_vien.label_so_dien_thoai',
					'translation_domain' => 'BinhLeAdmin'
				))
				           ->add('thanhVien.soDienThoaiSecours', null, array(
					           'label'              => 'thanh_vien.label_so_dien_thoai_secours',
					           'translation_domain' => 'BinhLeAdmin'
				           ))
				           ->add('thanhVien.diaChiThuongTru', null, array(
					           'label'              => 'thanh_vien.label_dia_chi_thuong_tru',
					           'translation_domain' => 'BinhLeAdmin'
				           ))
				           ->add('thanhVien.diaChiTamTru', null, array(
					           'label'              => 'thanh_vien.label_dia_chi_tam_tru',
					           'translation_domain' => 'BinhLeAdmin'
				           ));
			} else {
				$formMapper
					->add('lastname', null, [ 'required' => false ])
					->add('middlename', null, [ 'required' => false ])
					->add('firstname', null, [ 'required' => false ]);
			}
			
			$formMapper->end();
		}
		
	}
	
	/**
	 * @param User $object
	 */
	public function prePersist($object) {
		parent::prePersist($object);
		if( ! $object->isEnabled()) {
			$object->setEnabled(true);
		}
	}
	
	/**
	 * @param User $object
	 */
	public function preUpdate($object) {
		
		$this->getUserManager()->updateCanonicalFields($object);
		$this->getUserManager()->updatePassword($object);
		
		if( ! $object->isEnabled()) {
			$object->setEnabled(true);
		}
	}
	
	/**
	 * @return mixed
	 */
	public function getAction() {
		return $this->action;
	}
	
	/**
	 * @param mixed $action
	 */
	public function setAction($action) {
		$this->action = $action;
	}
	
	///////////////////////////////////
	///
	///
	///
	///////////////////////////////////
	/**
	 * @var UserManagerInterface
	 */
	protected $userManager;
	
	/**
	 * {@inheritdoc}
	 */
	public function getFormBuilder() {
		$this->formOptions['data_class'] = $this->getClass();
		
		$options                      = $this->formOptions;
		$options['validation_groups'] = ( ! $this->getSubject() || is_null($this->getSubject()->getId())) ? 'Registration' : 'Profile';
		
		$formBuilder = $this->getFormContractor()->getFormBuilder($this->getUniqid(), $options);
		
		$this->defineFormBuilder($formBuilder);
		
		return $formBuilder;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getExportFields() {
		// avoid security field to be exported
		return array_filter(parent::getExportFields(), function($v) {
			return ! in_array($v, [ 'password', 'salt' ]);
		});
	}
	
	
	/**
	 * {@inheritdoc}
	 */
	protected function configureDatagridFilters(DatagridMapper $filterMapper) {
		$filterMapper
			->add('id')
			->add('username')
//			->add('locked')
			->add('email');
//			->add('groups')
//		;
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function configureParentShowFields(ShowMapper $showMapper) {
		$showMapper
			->with('General')
			->add('username')
			->add('email')
			->end()
			->with('Groups')
			->add('groups')
			->end()
			->with('Profile')
			->add('dateOfBirth')
			->add('firstname')
			->add('lastname')
			->add('website')
			->add('biography')
			->add('gender')
			->add('locale')
			->add('timezone')
			->add('phone')
			->end()
			->with('Social')
			->add('facebookUid')
			->add('facebookName')
			->add('twitterUid')
			->add('twitterName')
			->add('gplusUid')
			->add('gplusName')
			->end()
			->with('Security')
			->add('token')
			->add('twoStepVerificationCode')
			->end();
	}
	
	
	/**
	 * @param UserManagerInterface $userManager
	 */
	public function setUserManager(UserManagerInterface $userManager) {
		$this->userManager = $userManager;
	}
	
	/**
	 * @return UserManagerInterface
	 */
	public function getUserManager() {
		return $this->userManager;
	}
	
}