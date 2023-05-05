<?php

namespace App\Controller;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommentController extends AbstractController
{
    #[Route('/comment/rating/{id}/{score}', name:'comment_rating')]
    public function rateComment(Request $request, Question $question, int $score,EntityManagerInterface $em){

        $question->setRating($question->getRating() + $score);
        $em->flush();

        $referer = $request->server->get('HTTP_REFERER'); 
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');

    }
}
