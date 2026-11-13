<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Service;
use App\Entity\Testimonial;
use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\ProductRepository;
use App\Repository\PromoRepository;
use App\Repository\ServiceRepository;
use App\Repository\TestimonialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StorefrontController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(
        ProductRepository $products,
        ServiceRepository $services,
        TestimonialRepository $testimonials,
    ): Response {
        $featuredProducts = array_map(
            fn (Product $product): array => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => is_numeric($product->getPrice()) ? (float) $product->getPrice() : 0,
                'stock' => $product->getStock(),
                'image' => $this->resolveHomepageImage($product->getImage(), true),
            ],
            $products->findHomepageProducts(),
        );
        $featuredServices = array_map(
            fn (Service $service): array => [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'description' => $service->getDescription() ?? '',
                'image' => $this->resolveHomepageImage($service->getImage()),
            ],
            $services->findHomepageServices(),
        );
        $approvedTestimonials = array_map(
            static function (Testimonial $testimonial): array {
                $subject = trim($testimonial->getSubject());
                $message = $testimonial->getMessage();
                if ($subject === '' && preg_match('/^Subject:\s*(.*?)\R\R(.*)$/s', $message, $matches)) {
                    $subject = trim($matches[1]);
                    $message = $matches[2];
                }

                return [
                    'name' => $testimonial->getName(),
                    'location' => 'Contact Page Review',
                    'subject' => $subject,
                    'message' => $message,
                    'image' => $testimonial->getImage() ?? '',
                ];
            },
            $testimonials->findApprovedLatest(4),
        );
        return $this->render('storefront/home.html.twig', array_merge([
            'products' => $featuredProducts,
            'services' => $featuredServices,
            'testimonials' => $approvedTestimonials,
        ], $this->authModalFormViews()));
    }

    private function resolveHomepageImage(?string $image, bool $prefixProductUpload = false): string
    {
        $image = trim($image ?? '');
        if ($prefixProductUpload && $image !== '' && basename(str_replace('\\', '/', $image)) === $image) {
            $image = 'uploads/'.$image;
        }

        $image = ltrim(str_replace(['\\', '../'], ['/', ''], $image), '/');
        $projectDir = dirname(__DIR__, 2);
        $publicPath = $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $image);
        $rootPath = $projectDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $image);

        return $image !== '' && (is_file($publicPath) || is_file($rootPath)) ? $image : '';
    }

    private function authModalFormViews(): array
    {
        $registrationForm = $this->createForm(RegistrationType::class, new User());
        $forgotPasswordForm = $this->createFormBuilder()
            ->add('email', EmailType::class, ['label' => false])
            ->getForm();

        return [
            'registrationForm' => $registrationForm->createView(),
            'forgotPasswordForm' => $forgotPasswordForm->createView(),
        ];
    }

    #[Route('/shop', name: 'shop_index', methods: ['GET'])]
    public function shop(ProductRepository $products): Response
    {
        $catalogProducts = array_map(
            fn (Product $product): array => [
                'product' => $product,
                'image' => $this->resolveHomepageImage($product->getImage(), true),
            ],
            $products->findCatalogProducts(),
        );

        return $this->render('storefront/shop.html.twig', array_merge([
            'products' => $catalogProducts,
        ], $this->authModalFormViews()));
    }

    #[Route('/products/{id}', name: 'product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function product(int $id, ProductRepository $products): Response
    {
        $product = $products->find($id);
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Product not found.');
        }

        return $this->render('storefront/product.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/services', name: 'service_index', methods: ['GET'])]
    public function services(ServiceRepository $services): Response
    {
        return $this->render('storefront/services.html.twig', array_merge([
            'services' => $services->findActiveServices(),
        ], $this->authModalFormViews()));
    }

    #[Route('/promos', name: 'promo_index', methods: ['GET'])]
    public function promos(PromoRepository $promos, ServiceRepository $services): Response
    {
        return $this->render('storefront/promos.html.twig', array_merge([
            'promos' => $promos->findActivePromos(),
            'bookingServices' => $services->findActiveServices(),
        ], $this->authModalFormViews()));
    }
}
