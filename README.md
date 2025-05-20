### Book Library E-Commerce Platform

A complete Symfony-based e-commerce platform for a book library with AI-powered book summaries.

## Overview

This project is a full-featured e-commerce application for selling books online. It includes user authentication, product management, shopping cart functionality, order processing, and AI-powered book summaries.





## Features

- **User Authentication**

- User registration and login
- Role-based access control (Admin/User)
- Secure password handling



- **Book Management**

- Complete CRUD operations for books
- Category organization
- Book details including cover images, descriptions, and pricing
- Stock management



- **AI-Powered Book Summaries**

- Integration with OpenAI API to generate book summaries
- Automatic summary generation when adding or editing books
- Manual summary generation option



- **Shopping Cart**

- Add/remove books from cart
- Update quantities
- Persistent cart using sessions



- **Order Processing**

- Checkout process
- Order history
- Order status tracking



- **Admin Dashboard**

- Manage books, categories, and orders
- View and update order statuses
- User management





## Installation

### Prerequisites

- PHP 8.1 or higher
- Composer
- MySQL or MariaDB
- Symfony CLI (optional, but recommended)


### Step 1: Clone the repository

```shellscript
git clone https://github.com/yourusername/book-library.git
cd book-library
```

### Step 2: Install dependencies

```shellscript
composer install
```

### Step 3: Configure environment variables

Create a `.env.local` file in the project root and configure your database and OpenAI API key:

```plaintext
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/book_library?serverVersion=8.0"
OPENAI_API_KEY=your_openai_api_key
```

### Step 4: Create the database and schema

```shellscript
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

### Step 5: Load fixtures (optional)

```shellscript
php bin/console doctrine:fixtures:load
```

This will create sample data including:

- Admin user: [admin@example.com](mailto:admin@example.com) / admin123
- Regular user: [user@example.com](mailto:user@example.com) / user123
- Sample books and categories


### Step 6: Create an admin user (if not using fixtures)

```shellscript
php bin/console app:create-admin admin@example.com password123 Admin User
```

### Step 7: Start the Symfony development server

```shellscript
symfony server:start
```

Or if you don't have the Symfony CLI:

```shellscript
php -S localhost:8000 -t public/
```

## Configuration

### AI Summary Configuration

The AI summary feature uses OpenAI's API to generate summaries for books. To configure it properly:

1. Get an API key from [OpenAI](https://platform.openai.com/account/api-keys)
2. Add it to your `.env.local` file:

```plaintext
OPENAI_API_KEY=your_openai_api_key
```




If you encounter issues with the OpenAI API or prefer not to use it, you can use the local summary generator:

1. Create the `LocalSummaryService.php` file in `src/Service/`:

```php
<?php

namespace App\Service;

class LocalSummaryService
{
    public function generateSummary(string $title, string $author, string $description): string
    {
        // Extract key sentences from the description
        $sentences = preg_split('/(?<=[.!?])\s+/', $description, -1, PREG_SPLIT_NO_EMPTY);
        
        // If description is short, just return it
        if (count($sentences) <= 3) {
            return $description;
        }
        
        // Otherwise, take the first sentence and a couple more important ones
        $summary = $sentences[0];
        
        // Add a middle sentence
        if (count($sentences) > 3) {
            $summary .= ' ' . $sentences[intval(count($sentences) / 2)];
        }
        
        // Add the last sentence
        $summary .= ' ' . $sentences[count($sentences) - 1];
        
        // Add a generic intro
        $intro = "\"" . $title . "\" by " . $author . " is a compelling work that captivates readers. ";
        
        return $intro . $summary;
    }
}
```


2. Update your `services.yaml` to use this service:

```yaml
# In config/services.yaml
services:
    # ... other services
    
    App\Service\AiSummaryService:
        class: App\Service\LocalSummaryService
```




## Usage

### User Guide

1. **Browse Books**

1. Visit the homepage to see all available books
2. Filter books by category
3. Search for specific titles or authors



2. **Book Details**

1. Click on a book to view its details
2. Read the AI-generated summary
3. See pricing and availability



3. **Shopping Cart**

1. Add books to your cart
2. Adjust quantities
3. Remove items
4. View cart total



4. **Checkout**

1. Login or register to proceed to checkout
2. Review your order
3. Complete the purchase





### Admin Guide

1. **Manage Books**

1. Add new books
2. Edit existing books
3. Delete books
4. Generate AI summaries



2. **Manage Categories**

1. Create new categories
2. Edit category details
3. Delete categories



3. **Manage Orders**

1. View all orders
2. Update order status
3. View order details





## Project Structure

```plaintext
book-library/
├── bin/
│   └── console
├── config/
│   ├── packages/
│   │   ├── security.yaml
│   │   └── ...
│   ├── routes.yaml
│   └── services.yaml
├── migrations/
├── public/
│   └── index.php
├── src/
│   ├── Command/
│   │   └── CreateAdminCommand.php
│   ├── Controller/
│   │   ├── BookController.php
│   │   ├── CartController.php
│   │   ├── CategoryController.php
│   │   ├── OrderController.php
│   │   ├── RegistrationController.php
│   │   └── SecurityController.php
│   ├── DataFixtures/
│   │   └── AppFixtures.php
│   ├── Entity/
│   │   ├── Book.php
│   │   ├── Category.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   └── User.php
│   ├── Form/
│   │   ├── BookType.php
│   │   ├── CategoryType.php
│   │   ├── OrderType.php
│   │   └── RegistrationFormType.php
│   ├── Repository/
│   │   ├── BookRepository.php
│   │   ├── CategoryRepository.php
│   │   ├── OrderItemRepository.php
│   │   ├── OrderRepository.php
│   │   └── UserRepository.php
│   ├── Security/
│   │   └── LoginFormAuthenticator.php
│   ├── Service/
│   │   ├── AiSummaryService.php
│   │   └── CartService.php
│   └── Kernel.php
├── templates/
│   ├── base.html.twig
│   ├── book/
│   ├── cart/
│   ├── category/
│   ├── order/
│   ├── registration/
│   └── security/
├── .env
├── .env.local
├── composer.json
└── symfony.lock
```

## Technologies Used

- **Backend**

- PHP 8.1+
- Symfony 6.x
- Doctrine ORM
- MySQL/MariaDB



- **Frontend**

- Twig Templates
- Bootstrap 5
- JavaScript



- **AI Integration**

- OpenAI API (GPT-3.5 Turbo)



- **Security**

- Symfony Security Bundle
- CSRF Protection
- Password Hashing





## Troubleshooting

### AI Summary Issues

If you encounter issues with the AI summary feature:

1. **Check your API key**

1. Ensure your OpenAI API key is valid and correctly set in `.env.local`



2. **Check API limits**

1. OpenAI has rate limits that might affect the functionality



3. **Switch to local summary generator**

1. Follow the instructions in the Configuration section to use the local summary generator



4. **Check error logs**

1. Look at `var/log/dev.log` for detailed error messages





### Database Issues

If you encounter database issues:

1. **Update the schema**

```shellscript
php bin/console doctrine:schema:update --force
```


2. **Clear the cache**

```shellscript
php bin/console cache:clear
```




## Contributing

Contributions are welcome! Here's how you can contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request


## Future Enhancements

- Payment gateway integration
- User reviews and ratings
- Advanced search functionality
- Recommendation system
- Email notifications
- PDF previews for books
- Mobile app integration


## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [Symfony](https://symfony.com/)
- [Bootstrap](https://getbootstrap.com/)
- [OpenAI](https://openai.com/)


---

Created by [Your Name] - [Your Email]
