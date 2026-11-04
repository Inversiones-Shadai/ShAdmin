import { loadSessionImage } from './session-image-cache';

const normalizePayload = (payload) => {
    if (Array.isArray(payload)) {
        return payload[0] ?? {};
    }

    return payload ?? {};
};

const printDocument = async (payload, label = 'ticket') => {
    const ticket = normalizePayload(payload);
    const html = typeof ticket.html === 'string' ? ticket.html : '';

    if (html.trim() === '') {
        window.ShAdminAlerts?.alert({
            icon: 'error',
            title: 'No fue posible imprimir',
            text: `La aplicación no pudo construir el formato de ${label}.`,
        });

        return;
    }

    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.position = 'fixed';
    frame.style.width = '1px';
    frame.style.height = '1px';
    frame.style.opacity = '0';
    frame.style.pointerEvents = 'none';
    frame.style.inset = '0';

    // Keep the document alive until the print dialog closes.
    const removeFrame = () => frame.remove();

    frame.addEventListener('load', async () => {
        const frameDocument = frame.contentDocument;

        if (!frameDocument) {
            removeFrame();

            return;
        }

        try {
            await Promise.all([...frameDocument.images].map(async (img) => {
                const source = img.getAttribute('src') || '';
                if (!source || source.startsWith('data:')) return;
                try { img.src = await loadSessionImage(source); } catch { /* Keep the direct storage URL. */ }
            }));
            await Promise.all([...frameDocument.images].map((img) => img.decode().catch(() => {})));
            await frameDocument.fonts?.ready;
            if (frameDocument.body?.dataset.printPaper === 'thermal') {
                // Fit short receipts to the roll; paginate long transfers at 297 mm.
                const height = Math.ceil(frameDocument.body.getBoundingClientRect().height * 25.4 / 96) + 8;
                const paperStyle = frameDocument.createElement('style');
                paperStyle.textContent = `@page { size: 80mm ${Math.max(80, Math.min(height, 297))}mm; margin: 4mm; }`;
                frameDocument.head.append(paperStyle);
            }
            const printWindow = frame.contentWindow;
            if (!printWindow) {
                removeFrame();
                return;
            }
            printWindow.addEventListener('afterprint', removeFrame, { once: true });
            printWindow.focus();
            printWindow.print();
        } catch {
            removeFrame();
            window.ShAdminAlerts?.alert({
                icon: 'error',
                title: 'No fue posible imprimir',
                text: `El navegador no pudo abrir el diálogo de impresión. Intenta imprimir ${label} nuevamente.`,
            });
        }
    }, { once: true });

    const printable = new DOMParser().parseFromString(html, 'text/html');
    await Promise.all([...printable.images].map(async (image) => {
        const source = image.getAttribute('src') || '';
        if (!source || source.startsWith('data:')) return;
        try { image.src = await loadSessionImage(source); } catch { /* Use the direct URL if CORS is unavailable. */ }
    }));
    frame.srcdoc = '<!DOCTYPE html>\n' + printable.documentElement.outerHTML;
    document.body.append(frame);
};

const registerTicketPrinter = () => {
    if (!window.Livewire) {
        return;
    }

    if (!window.__shAdminTicketPrinterListener) {
        window.__shAdminTicketPrinterListener = window.Livewire.on('ticket:print', (payload) => printDocument(payload));
    }
    if (!window.__shAdminTransferPrinterListener) {
        window.__shAdminTransferPrinterListener = window.Livewire.on('transfer:print', (payload) => printDocument(payload, 'transferencia'));
    }
};

document.addEventListener('livewire:init', registerTicketPrinter);
registerTicketPrinter();
