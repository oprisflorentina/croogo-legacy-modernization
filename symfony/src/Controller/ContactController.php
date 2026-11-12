<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Message;
use App\Form\ContactFormType;
use App\Service\CroogoSettings;
use App\Service\LegacyLayout;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The public contact form, moved from Croogo's ContactsController::view().
 *
 * Same steps in the same order: find the published "contact" form, validate
 * the message, store it if the form archives messages, email it if the form
 * notifies, then show the confirmation. Croogo's spam check (Akismet) and
 * captcha (reCAPTCHA v1, shut down in 2018) are not moved; both are off.
 */
final class ContactController extends AbstractController
{
    private const RECEIVED = 'Your message has been received...';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly MailerInterface $mailer,
        private readonly LegacyLayout $layout,
        private readonly CroogoSettings $settings,
    ) {
    }

    #[Route('/contact', name: 'contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $contact = $this->entityManager->getRepository(Contact::class)
            ->findOneBy(['alias' => 'contact', 'status' => true]);
        if (!$contact instanceof Contact) {
            return $this->redirect('/');
        }

        $message = new Message($contact);
        // Named "data", so the fields are data[Message][name] and so on.
        $form = $this->formFactory->createNamed('data', ContactFormType::class, ['Message' => $message]);

        if ($contact->acceptsMessages()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid() && $this->store($contact, $message) && $this->notify($contact, $message, $request)) {
                return $this->render('contact/received.html.twig', ['message' => self::RECEIVED, 'url' => '/']);
            }
        }

        // Rendered with status 200 even with errors, as Croogo did.
        $content = $this->renderView('contact/view.html.twig', ['contact' => $contact, 'form' => $form]);

        return new Response($this->layout->wrap($request->getPathInfo(), $contact->getTitle(), $content));
    }

    /** Stores the message if the form archives messages. */
    private function store(Contact $contact, Message $message): bool
    {
        if ($contact->archivesMessages()) {
            $this->entityManager->persist($message);
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * Emails the message to the form's address if the form notifies. Address
     * quotes the visitor's name where needed, so a name that looks like an
     * address can't take the visitor's place as the sender.
     */
    private function notify(Contact $contact, Message $message, Request $request): bool
    {
        if (!$contact->notifiesByEmail()) {
            return true;
        }

        $email = (new Email())
            ->from(new Address((string) $message->getEmail(), (string) $message->getName()))
            ->to((string) $contact->getEmail())
            ->subject(sprintf('[%s] %s', $this->settings->get('Site.title'), $contact->getTitle()))
            ->text($this->renderView('emails/contact.txt.twig', [
                'url' => $this->generateUrl('contact', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'message' => $message,
                // The connection's own address: no proxy headers are trusted.
                'ip' => $request->getClientIp(),
            ]));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // As in Croogo: no confirmation, the form is shown again.
            return false;
        }

        return true;
    }
}
