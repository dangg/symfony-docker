<?php

namespace App\Controller;

use \Doctrine\ORM\EntityManagerInterface;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use \Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/", name="index")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $tables = $this->entityManager->getConnection()->executeQuery("SHOW TABLES")->fetchAll();
        return new Response(var_export($tables, true));
    }
}