<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\BookForm;
use App\Form\BookType;
use App\Repository\BookRepository;
use App\Service\HybridSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/book')]
class BookController extends AbstractController
{
    private $summaryService;

    public function __construct(HybridSummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    #[Route('/', name: 'app_book_index', methods: ['GET'])]
    public function index(BookRepository $bookRepository): Response
    {
        return $this->render('book/index.html.twig', [
            'books' => $bookRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_book_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $book = new Book();
        $form = $this->createForm(BookForm::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate summary if description is provided
            if ($book->getDescription()) {
                try {
                    $summary = $this->summaryService->generateSummary(
                        $book->getTitle(),
                        $book->getAuthor(),
                        $book->getDescription()
                    );
                    
                    // Check if summary starts with error message 
                    if (strpos($summary, 'Unable to generate') === 0) {
                        $this->addFlash('warning', $summary);
                    } else {
                        $book->setAiSummary($summary);
                    }
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Could not generate summary: ' . $e->getMessage());
                }
            }

            $entityManager->persist($book);
            $entityManager->flush();

            return $this->redirectToRoute('app_book_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('book/new.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_book_show', methods: ['GET'])]
    public function show(Book $book): Response
    {
        return $this->render('book/show.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_book_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Book $book, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(BookForm::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Generate summary if description is provided and summary is empty
            if ($book->getDescription() && !$book->getAiSummary()) {
                try {
                    $summary = $this->summaryService->generateSummary(
                        $book->getTitle(),
                        $book->getAuthor(),
                        $book->getDescription()
                    );
                    
                    // Check if summary starts with error message
                    if (strpos($summary, 'Unable to generate') === 0) {
                        $this->addFlash('warning', $summary);
                    } else {
                        $book->setAiSummary($summary);
                    }
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Could not generate summary: ' . $e->getMessage());
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_book_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('book/edit.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_book_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Book $book, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$book->getId(), $request->request->get('_token'))) {
            $entityManager->remove($book);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_book_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/generate-summary', name: 'app_book_generate_summary', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function generateSummary(Book $book, EntityManagerInterface $entityManager): Response
    {
        if (!$book->getDescription()) {
            $this->addFlash('error', 'Book description is required to generate a summary.');
            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        try {
            $summary = $this->summaryService->generateSummary(
                $book->getTitle(),
                $book->getAuthor(),
                $book->getDescription()
            );
            
            // Check if the summary indicates an error
            if (strpos($summary, 'Unable to generate') === 0) {
                $this->addFlash('warning', $summary);
            } else {
                $book->setAiSummary($summary);
                $entityManager->flush();
                $this->addFlash('success', 'Summary generated successfully.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Could not generate summary: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }
}