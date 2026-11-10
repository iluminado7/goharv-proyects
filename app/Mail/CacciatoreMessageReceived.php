<?php

namespace App\Mail;

use App\Models\CacciatoreMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso al estudio de que llego una consulta por cacciatoreabogados.com.ar.
 *
 * Sale DESDE una direccion del estudio (dominio verificado en Resend) y no
 * desde la del visitante: mandar "como" otra persona rompe SPF y DMARC, y el
 * mail termina en spam o rebotado. La del visitante va en Reply-To, asi que
 * "Responder" le contesta a el.
 */
class CacciatoreMessageReceived extends Mailable
{
    public function __construct(public CacciatoreMessage $consulta) {}

    public function envelope(): Envelope
    {
        $from = config('services.cacciatore.mail_from');

        return new Envelope(
            from: filled($from) ? new Address($from, 'Web Cacciatore & Cacciatore') : null,
            replyTo: [new Address($this->consulta->email, $this->consulta->deQuien())],
            subject: 'Nueva consulta web: '.$this->consulta->deQuien().' ('.$this->consulta->company.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.consulta-cacciatore',
            text: 'mail.consulta-cacciatore-texto',
        );
    }
}
