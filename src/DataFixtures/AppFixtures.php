<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $adminUser = new User();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setPassword($this->passwordHasher->hashPassword($adminUser, 'admin123'));
        $adminUser->setFirstName('Admin');
        $adminUser->setLastName('User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $manager->persist($adminUser);

        // Create regular user
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
        $user->setFirstName('Regular');
        $user->setLastName('User');
        $manager->persist($user);

        // Create categories
        $categories = [];
        $categoryNames = ['Fiction', 'Non-Fiction', 'Science Fiction', 'Mystery', 'Biography', 'History', 'Self-Help'];
        
        foreach ($categoryNames as $name) {
            $category = new Category();
            $category->setName($name);
            $category->setDescription('Books in the ' . $name . ' category');
            $manager->persist($category);
            $categories[] = $category;
        }

        // Create books
        $books = [
            [
                'title' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'description' => 'Set in the Jazz Age on Long Island, the novel depicts narrator Nick Carraway\'s interactions with mysterious millionaire Jay Gatsby and Gatsby\'s obsession to reunite with his former lover, Daisy Buchanan.',
                'price' => 12.99,
                'stock' => 50,
                'isbn' => '9780743273565',
                'category' => $categories[0], // Fiction
                'coverImage' => 'https://m.media-amazon.com/images/I/71FTb9X6wsL._AC_UF1000,1000_QL80_.jpg'
            ],
            [
                'title' => 'To Kill a Mockingbird',
                'author' => 'Harper Lee',
                'description' => 'The story of young Scout Finch, her brother Jem, and their father Atticus, as they navigate issues of race and class in their small Southern town during the 1930s.',
                'price' => 14.95,
                'stock' => 75,
                'isbn' => '9780061120084',
                'category' => $categories[0], // Fiction
                'coverImage' => 'https://m.media-amazon.com/images/I/71FxgtFKcQL._AC_UF1000,1000_QL80_.jpg'
            ],
            [
                'title' => 'Sapiens: A Brief History of Humankind',
                'author' => 'Yuval Noah Harari',
                'description' => 'A book that explores the history of the human species from the emergence of Homo sapiens in Africa to the political and technological revolutions of the 21st century.',
                'price' => 24.99,
                'stock' => 30,
                'isbn' => '9780062316097',
                'category' => $categories[1], // Non-Fiction
                'coverImage' => 'https://m.media-amazon.com/images/I/71N3-2sYDRL._AC_UF1000,1000_QL80_.jpg'
            ],
            [
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'description' => 'Set on the desert planet Arrakis, Dune is the story of the boy Paul Atreides, heir to a noble family tasked with ruling an inhospitable world where the only thing of value is the "spice" melange.',
                'price' => 18.50,
                'stock' => 40,
                'isbn' => '9780441172719',
                'category' => $categories[2], // Science Fiction
                'coverImage' => 'https://m.media-amazon.com/images/I/81ym3QUd3KL._AC_UF1000,1000_QL80_.jpg'
            ],
            [
                'title' => 'The Silent Patient',
                'author' => 'Alex Michaelides',
                'description' => 'A psychological thriller about a woman\'s act of violence against her husband—and of the therapist obsessed with uncovering her motive.',
                'price' => 16.99,
                'stock' => 25,
                'isbn' => '9781250301697',
                'category' => $categories[3], // Mystery
                'coverImage' => 'https://m.media-amazon.com/images/I/91lslnZ-btL._AC_UF1000,1000_QL80_.jpg'
            ],
            [
                'title' => 'Steve Jobs',
                'author' => 'Walter Isaacson',
                'description' => 'The biography of Apple co-founder Steve Jobs, based on more than forty interviews with Jobs conducted over two years.',
                'price' => 22.00,
                'stock' => 20,
                'isbn' => '9781451648539',
                'category' => $categories[4], // Biography
                'coverImage' => 'https://m.media-amazon.com/images/I/41n1edvVlLL._AC_UF1000,1000_QL80_.jpg'
            ],
            [
                'title' => 'The Subtle Art of Not Giving a F*ck',
                'author' => 'Mark Manson',
                'description' => 'A self-help book that challenges the conventional wisdom about positive thinking and encourages readers to become comfortable with their flaws and limitations.',
                'price' => 19.99,
                'stock' => 60,
                'isbn' => '9780062457714',
                'category' => $categories[6], // Self-Help
                'coverImage' => 'https://m.media-amazon.com/images/I/71QKQ9mwV7L._AC_UF1000,1000_QL80_.jpg'
            ],
        ];

        foreach ($books as $bookData) {
            $book = new Book();
            $book->setTitle($bookData['title']);
            $book->setAuthor($bookData['author']);
            $book->setDescription($bookData['description']);
            $book->setPrice($bookData['price']);
            $book->setStock($bookData['stock']);
            $book->setIsbn($bookData['isbn']);
            $book->setCategory($bookData['category']);
            $book->setCoverImage($bookData['coverImage']);
            
            // Generate a fake AI summary
            $book->setAiSummary('This is a simulated AI-generated summary for ' . $bookData['title'] . ' by ' . $bookData['author'] . '. In a real application, this would be generated by an AI service based on the book\'s description.');
            
            $manager->persist($book);
        }

        $manager->flush();
    }
}