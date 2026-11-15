<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $templateKey,
        public array $company = [],
        public array $payment = [],
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'invoice' => 'Facture – commande ' . $this->order->order_number,
            'pending_verification' => 'Paiement en cours de vérification – ' . $this->order->order_number,
            'receipt' => 'Merci – reçu de votre commande ' . $this->order->order_number,
            'admin_new_order' => 'Nouvelle commande ' . $this->order->order_number,
            'order_received' => 'Commande reçue – ' . $this->order->order_number,
            'receipt_rejected' => 'Preuve de paiement refusée – ' . $this->order->order_number,
            'order_approved' => 'Commande confirmée – ' . $this->order->order_number,
            'order_shipped' => 'Votre commande a été expédiée',
            'order_delivered' => 'Votre commande a été livrée',
        ];

        return new Envelope(
            subject: $subjects[$this->templateKey] ?? 'Mise à jour de commande',
        );
    }

    public function content(): Content
    {
        $copy = [
            'invoice' => [
                'heading' => 'Votre facture',
                'intro' => 'Votre commande est enregistrée. Merci de procéder au virement avec les coordonnées ci-dessous, puis de nous transmettre la preuve de paiement si vous ne l’avez pas déjà fait.',
                'closing' => 'Dès réception et vérification du paiement, vous recevrez un reçu de confirmation.',
            ],
            'pending_verification' => [
                'heading' => 'Paiement en cours de vérification',
                'intro' => 'Nous avons bien reçu votre preuve de paiement. Notre équipe la vérifie. Vous serez informé dès validation.',
                'closing' => 'Aucune action n’est requise de votre part pour le moment.',
            ],
            'receipt' => [
                'heading' => 'Merci pour votre commande',
                'intro' => 'Votre paiement a été vérifié. Voici le reçu de votre commande. Nous préparons l’expédition.',
                'closing' => 'Merci de votre confiance.',
            ],
            'admin_new_order' => [
                'heading' => 'Nouvelle commande',
                'intro' => 'Une nouvelle commande vient d’être passée. Connectez-vous à l’administration pour la traiter.',
                'closing' => 'Notification automatique.',
            ],
        ];

        $block = $copy[$this->templateKey] ?? [
            'heading' => 'Mise à jour de commande',
            'intro' => 'Votre commande a été mise à jour.',
            'closing' => 'Merci.',
        ];

        return new Content(
            view: 'emails.order',
            with: [
                'order' => $this->order,
                'templateKey' => $this->templateKey,
                'company' => $this->company,
                'payment' => $this->payment,
                'subjectLine' => $this->envelope()->subject,
                'heading' => $block['heading'],
                'intro' => $block['intro'],
                'closing' => $block['closing'],
            ],
        );
    }
}
