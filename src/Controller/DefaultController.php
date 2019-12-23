<?php

namespace App\Controller;

use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use \Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{

    /**
     * @Route("/", name="index")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return new Response(var_export("Hello World!", true));
    }
}