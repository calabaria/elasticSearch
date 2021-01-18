<?php declare(strict_types=1);

// src/Controller/SearchPart1Controller.php

namespace App\Controller;

use Elastica\Util;
use FOS\ElasticaBundle\Finder\TransformedFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;


final class SearchController extends AbstractController
{
    /**
     * @Route("/",  name="search")
     */
    public function search(Request $request, SessionInterface $session, TransformedFinder $articlesFinder): Response
    {
        $q = (string) $request->query->get('q', '');
        $results = !empty($q) ? $articlesFinder->findHybrid(Util::escapeTerm($q)) : [];

        $session->set('q', $q);
        $compacted = compact('results', 'q');
        return $this->render('search/index.html.twig', $compacted);
    }

    /**
     * @Route("/search",  name="searchAjax")
     */
    /*public function searchAjax(Request $request, SessionInterface $session, TransformedFinder $articlesFinder): Response
    {
        $q = (string) $request->query->get('q', '');
        $results = !empty($q) ? $articlesFinder->findHybrid(Util::escapeTerm($q)) : [];
        $session->set('q', $q);
        $compacted = compact('results', 'q');
        return $this->render('search/qajax.html.twig', $compacted);
    }*/
}