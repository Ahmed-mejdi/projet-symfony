<?php

namespace App\Service;

use App\Entity\Book;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }

    public function getCart(): array
    {
        return $this->getSession()->get('cart', []);
    }

    public function add(int $id, int $quantity = 1): void
    {
        $cart = $this->getCart();

        if (!isset($cart[$id])) {
            $cart[$id] = 0;
        }

        $cart[$id] += $quantity;

        $this->getSession()->set('cart', $cart);
    }

    public function remove(int $id): void
    {
        $cart = $this->getCart();

        if (isset($cart[$id])) {
            unset($cart[$id]);
        }

        $this->getSession()->set('cart', $cart);
    }

    public function update(int $id, int $quantity): void
    {
        $cart = $this->getCart();

        if ($quantity <= 0) {
            $this->remove($id);
            return;
        }

        $cart[$id] = $quantity;
        $this->getSession()->set('cart', $cart);
    }

    public function clear(): void
    {
        $this->getSession()->set('cart', []);
    }
}