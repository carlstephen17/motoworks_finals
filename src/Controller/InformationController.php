<?php

namespace App\Controller;

use App\Entity\Testimonial;
use App\Repository\TestimonialRepository;
use App\Service\ImageUploader;
use App\Validator\ImageFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\File;

class InformationController extends AbstractController
{
    #[Route('/about', name: 'page_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('information/about.html.twig');
    }

    #[Route('/team', name: 'page_team', methods: ['GET'])]
    public function team(): Response
    {
        return $this->render('information/team.html.twig');
    }

    #[Route('/battery', name: 'page_battery', methods: ['GET'])]
    public function battery(): Response
    {
        return $this->render('information/battery.html.twig');
    }

    #[Route('/oil', name: 'page_oil', methods: ['GET'])]
    public function oil(): Response
    {
        return $this->render('information/oil.html.twig');
    }

    #[Route('/tires', name: 'page_tires', methods: ['GET'])]
    public function tires(): Response
    {
        return $this->render('information/tires.html.twig');
    }

    #[Route('/cooling', name: 'page_cooling', methods: ['GET'])]
    public function cooling(): Response
    {
        return $this->render('information/cooling.html.twig');
    }

    #[Route('/faq', name: 'page_faq', methods: ['GET'])]
    public function faq(): Response
    {
        return $this->render('information/faq.html.twig');
    }

    #[Route('/privacy', name: 'page_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('information/privacy.html.twig');
    }

    #[Route('/terms', name: 'page_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('information/terms.html.twig');
    }

    #[Route('/terms-of-service', name: 'page_terms_of_service', methods: ['GET'])]
    public function termsOfService(): Response
    {
        return $this->render('information/terms_of_service.html.twig');
    }

    #[Route('/contact', name: 'page_contact', methods: ['GET', 'POST'])]
    public function contact(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageUploader $imageUploader,
    ): Response {
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, ['label' => 'Full name'])
            ->add('email', EmailType::class, ['label' => 'Email address'])
            ->add('subject', TextType::class, ['required' => false])
            ->add('message', TextareaType::class, ['label' => 'Message'])
            ->add('imageFile', FileType::class, [
                'label' => 'Photo (optional)',
                'mapped' => false,
                'required' => false,
                'constraints' => [new File(maxSize: '5M'), new ImageFile()],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $name = trim((string) $data['name']);
            $email = trim((string) $data['email']);
            $subject = trim((string) $data['subject']);
            $message = trim((string) $data['message']);

            if ($name === '' || mb_strlen($name) > 255 || mb_strlen($email) > 100 || mb_strlen($subject) > 255 || $message === '' || mb_strlen($message) > 10000) {
                $this->addFlash('error', 'Please check the name, email, subject, and message lengths.');
            } else {
                $testimonial = (new Testimonial($name, $email, $subject, $message))
                    ->setStatus('approved');
                $image = $form->get('imageFile')->getData();
                if ($image instanceof UploadedFile) {
                    $testimonial->setImage($imageUploader->upload($image, 'images/testimonials'));
                }
                $entityManager->persist($testimonial);
                $entityManager->flush();
                $this->addFlash('success', 'Thank you! Your message and review have been submitted successfully.');

                return $this->redirectToRoute('page_contact');
            }
        }

        return $this->render('information/contact.html.twig', ['form' => $form]);
    }
}
