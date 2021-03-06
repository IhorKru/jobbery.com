<?php

namespace AppBundle\Controller;

use DateTime;
use Doctrine\DBAL\DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\SubscriberDetails;
use AppBundle\Entity\SubscriberOptOutDetails;
use AppBundle\Entity\SubscriberOptInDetails;
use AppBundle\Form\SubscriberType;
use AppBundle\Form\SubscriberOptOutType;
use Swift_Message;

class FrontEndController extends Controller
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction(Request $request)
    {
        $error = 0;
        try {
            $newSubscriber = new SubscriberDetails();
                
            $form = $this->createForm(SubscriberType::class, $newSubscriber, [
                'action' => $this -> generateUrl('index'),
                'method' => 'POST']);

            $form->handleRequest($request);
            
            //validating data in the form and sending email
            if($form->isSubmitted() && $form->isValid())
            {
                //getting data from the form

                $phone = $form['phone']->getData();

                $hash = $this->mc_encrypt($newSubscriber->getEmailAddress(), $this->generateKey(16));
                $em = $this->getDoctrine()->getManager();
                $exsSubscriber = $em->getRepository('AppBundle:SubscriberDetails') ->findOneBy(['emailaddress' => $emailaddress]);
                if(!$exsSubscriber) {
                    //if user does not exist -> collect user details
                    $query = $em ->createQuery('SELECT MAX(s.id) FROM AppBundle:SubscriberDetails s');
                    $newSubscriber ->setId($query->getSingleScalarResult() + 1);
                    $newSubscriber ->setPhone($phone);
                    $newSubscriber ->setSourceid(1);

                    //pusshing data through to the database
                    $em->persist($newSubscriber);
                    $em->flush();
                } else {
                    //if user does not exist for this resource
                    $userid = $exsSubscriber ->getId();
                    $isopted = $em ->getRepository('AppBundle:SubscriberOptInDetails') ->findOneBy(['user' => $userid, 'resourceid' => 1]);
                    if(!$isopted) {
                        //if user does not exist under other resources as well -> collect optin details
                        $query2 = $em ->createQuery('SELECT MAX(t.id) FROM AppBundle:SubscriberOptInDetails t');
                        //pushing optin details to db
                    } else {
                        //if user already exists under this resource
                        $newContact = new Contact();
                        $form2 = $this->createForm(ContactType::class, $newContact, [
                            'action' => $this -> generateUrl('index'),
                            'method' => 'POST'
                        ]);
                        return $this->render('FrontEnd/userexists.html.twig',[
                            'form2'=>$form2->createView(),
                            'name' => $newSubscriber->getFirstname(),
                            'lastname' => $newSubscriber->getLastname(),
                            'email' => $newSubscriber->getEmailAddress()
                        ]);
                    }
                }
                //verify code

                //generating successfull responce page
                return $this->redirect($this->generateUrl('thankureg'));
            }
        //catching errors         
        } catch(Exception $ex) {
        $error = 1;
        //this will be activated in case user already exists in the database
        } catch(DBALException $e) {
         $error =1;
        }
        
        return $this->render('FrontEnd/index.html.twig', [
            'form' => $form->createView(),
            'error' => $error
            ]);
    }
    
    /**
     * @Route("verify/{emailaddress}")
     * @Method("GET")
     */
    public function verifyEmailAction(Request $request, $emailaddress) {
        $newOptInDetails = new SubscriberOptInDetails();
        $subscriber = new SubscriberDetails();
        
        $em = $this->getDoctrine()->getManager();
        $subscriber = $em->getRepository('AppBundle:SubscriberDetails') ->findOneBy(['emailaddress' => $emailaddress]);
        $userid = $subscriber ->getId();

        if(!$subscriber) {
            throw $this->createNotFoundException('U bettr go awai!');
        } else {
            $newOptInDetails = $em ->getRepository('AppBundle:SubscriberOptInDetails') ->findOneBy(['user' => $userid, 'resourceid' => 1]);
            $newOptInDetails ->setOptindate(new DateTime());
            $newOptInDetails ->setOptinip($_SERVER['REMOTE_ADDR']);
            $em->persist($newOptInDetails);
            $em->flush();
            return $this->redirect($this->generateUrl('index'));
        } 
    }
    
    /**
    * @Route("terms", name="terms")
    */
    public function termsAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('FrontEnd/terms.html.twig');
    }
    
    /**
    * @Route("privacy", name="privacy")
    */
    public function privacyAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('FrontEnd/privacy.html.twig');
    }
    
    /**
    * @Route("thankureg", name="thankureg")
    */
    public function thankuregAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('FrontEnd/thankureg.html.twig');
    }
    
     /**
     * @Route("verify/unsubscribe/{emailaddress}")
     * @Method("GET")
     */
    public function verifyUnsubscribeAction(Request $request, $emailaddress) {
        $newOptOutDetails = new SubscriberOptOutDetails();
        $em = $this->getDoctrine()->getManager();
        $subscriber = $em->getRepository('AppBundle:SubscriberDetails') ->findOneBy(['emailaddress' => $emailaddress]);
        
        if(!$subscriber) {
            throw $this->createNotFoundException('U bettr go awai!');
        } else {
            $query3 = $em ->createQuery('SELECT MAX(u.id) FROM AppBundle:SubscriberOptOutdetails u');
            $newOptOutDetails ->setId($query3->getSingleScalarResult() + 1);
            $newOptOutDetails ->setEmailAddress($emailaddress);
            $newOptOutDetails ->setUser($subscriber);
            $newOptOutDetails ->setResourceid(1);
            $newOptOutDetails ->setOptoutdate(new DateTime());
            $newOptOutDetails ->setOptoutip($_SERVER['REMOTE_ADDR']);
            $em->persist($newOptOutDetails);        
            $em->flush();
        }

        return $this->redirect($this->generateUrl('index'));
    }
    
    /**
    * @Route("unsubscribe", name="unsubscribe")
    */
    public function unsubscribeAction(Request $request) {   
        $error = 0;
        $unsubscriber = new SubscriberOptOutDetails();
        
        $form = $this->createForm(SubscriberOptOutType::class, $unsubscriber, [
            'action' => $this->generateUrl('unsubscribe'),
            'method' => 'POST'
            ]);
        
        $form->handleRequest($request);
        
        if($form->isValid() && $form->isSubmitted()) {
            $em = $this->getDoctrine()->getManager();
            $subscriber = $em->getRepository('AppBundle:SubscriberDetails')->findOneByEmailaddress($unsubscriber->getEmailaddress());

            if($subscriber) {
                    $urlButton = $this->generateEmailUrl(($request->getLocale() === 'ru' ? '/ru/' : '/') . 'verify/unsubscribe/' . $subscriber->getEmailAddress() . '?id=' . urlencode($subscriber->getHash()));
                    $message = Swift_Message::newInstance()
                        ->setSubject('Jobbery | We are sorry you are leaving us')
                        ->setFrom(['support@jobbery.com' => 'Jobbery Support Team'])
                        ->setTo($subscriber->getEmailAddress())
                        ->setContentType("text/html")
                        ->setBody($this->renderView('FrontEnd/emailUnsubscribe.html.twig', [
                            'url' => $urlButton, 
                            'name' => $subscriber->getFirstname(),
                            'lastname' => $subscriber->getLastname(),
                            'email' => $subscriber->getEmailAddress()
                            ]));

                    $this->get('mailer')->send($message);
                    return $this->redirect($this->generateUrl('sorryunsubscribe'));
            } else {
                $error = 1;
            }
        }

        return $this->render('FrontEnd/unsubscribe.html.twig', [
            'form' => $form->createView(),
            'error' => $error
            ]);

    }    
    
    /**
    * @Route("sorryunsubscribe", name="sorryunsubscribe")
    */
    public function sorryunsubscribeAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('FrontEnd/sorryunsubscribe.html.twig');
    }
    
    //controller specific functions
    
    private function generateKey($size) {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = "";
        for($i = 0; $i < $size; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
    
     private function mc_encrypt($encrypt, $key) {
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
        $passcrypt = trim(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, trim($encrypt), MCRYPT_MODE_ECB, $iv));
        $encode = base64_encode($passcrypt);
        return $encode;
    }
    
    private function generateEmailUrl($url) {
        return "http://jobbery.mediaff.com" . $this->container->get('router')->getContext()->getBaseUrl() . $url;
    }
      
}