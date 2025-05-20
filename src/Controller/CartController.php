<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\BookRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    #[Route('/', name: 'app_cart_index', methods: ['GET'])]
    public function index(CartService $cartService, BookRepository $bookRepository): Response
    {
        $cart = $cartService->getCart();
        $cartItems = [];
        $total = 0;

        foreach ($cart as $id => $quantity) {
            $book = $bookRepository->find($id);
            if ($book) {
                $cartItems[] = [
                    'book' => $book,
                    'quantity' => $quantity,
                    'subtotal' => $book->getPrice() * $quantity
                ];
                $total += $book->getPrice() * $quantity;
            }
        }

        return $this->render('cart/index.html.twig', [
            'items' => $cartItems,
            'total' => $total,
        ]);
    }

    #[Route('/add/{id}', name: 'app_cart_add', methods: ['GET', 'POST'])]
    public function add(Request $request, Book $book, CartService $cartService): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $cartService->add($book->getId(), $quantity);

        $this->addFlash('success', 'Book added to cart.');

        return $this->redirectToRoute('app_book_index');
    }

    #[Route('/remove/{id}', name: 'app_cart_remove', methods: ['GET'])]
    public function remove(Book $book, CartService $cartService): Response
    {
        $cartService->remove($book->getId());

        $this->addFlash('success', 'Item removed from cart.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/update/{id}', name: 'app_cart_update', methods: ['POST'])]
    public function update(Request $request, Book $book, CartService $cartService): Response
    {
        $quantity = $request->request->getInt('quantity', 1);
        $cartService->update($book->getId(), $quantity);

        $this->addFlash('success', 'Cart updated.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/clear', name: 'app_cart_clear', methods: ['GET'])]
    public function clear(CartService $cartService): Response
    {
        $cartService->clear();

        $this->addFlash('success', 'Cart cleared.');

        return $this->redirectToRoute('app_cart_index');
    }

    #[Route('/checkout', name: 'app_cart_checkout', methods: ['GET'])]
    public function checkout(CartService $cartService, BookRepository $bookRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $cart = $cartService->getCart();
        
        if (empty($cart)) {
            $this->addFlash('error', 'Your cart is empty.');
            return $this->redirectToRoute('app_cart_index');
        }

        $order = new Order();
        $order->setUser($this->getUser());
        $order->setStatus('pending');
        $order->setOrderDate(new \DateTime());
        
        $entityManager->persist($order);

        foreach ($cart as $id => $quantity) {
            $book = $bookRepository->find($id);
            if ($book && $book->getStock() >= $quantity) {
                $orderItem = new OrderItem();
                $orderItem->setBook($book);
                $orderItem->setQuantity($quantity);
                $orderItem->setPrice($book->getPrice());
                $orderItem->setOrderRef($order);
                
                $entityManager->persist($orderItem);
                
                // Update book stock
                $book->setStock($book->getStock() - $quantity);
            } else {
                $this->addFlash('error', 'Not enough stock for ' . $book->getTitle());
                return $this->redirectToRoute('app_cart_index');
            }
        }

        $entityManager->flush();
        $cartService->clear();

        $this->addFlash('success', 'Order placed successfully!');

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }
}