<?php


namespace BrauneDigital\ActivityBundle\Controller;

use BrauneDigital\ActivityBundle\Entity\Stream\Activity;
use Sonata\AdminBundle\Controller\CRUDController as Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ActivityCrudController extends Controller
{

	/**
	 * @param $status
	 * @return RedirectResponse
	 * @throws AccessDeniedException
	 */
	public function changeStateAction($state) {

		$object = $this->admin->getSubject();

		if (false === $this->admin->isGranted('CHANGE_STATUS')) {
			throw new AccessDeniedException();
		}

		$em = $this->container->get('doctrine')->getManager();
		$allowed = false;
		if (
		in_array($object->getReviewState(), array(Activity::REVIEW_STATE_UNREVIEWED, Activity::REVIEW_STATE_APPROVED, Activity::REVIEW_STATE_REJECTED))
		) {
			$allowed = true;
		}

		if ($allowed) {
			$object->setReviewState($state);
			$em->persist($object);
			$em->flush();
			$this->addFlash("sonata_flash_success", "The Status has been changed.");
		} else {
			$this->addFlash("sonata_flash_error", "An error occured.");
		}

		return new RedirectResponse($this->admin->generateUrl('list', array('filter' => $this->admin->getFilterParameters())));

	}

}

?>