export default function QRDisplay({image,url}:{image:string;url:string}) {
    return <figure className="ticket-qr"><img src={image} width={280} height={280} alt="QR code linking to public ticket verification"/><figcaption><a className="text-link" href={url} target="_blank" rel="noreferrer">Open public verification</a><p className="field-hint">Scan to check the latest ticket and payment status.</p></figcaption></figure>;
}
