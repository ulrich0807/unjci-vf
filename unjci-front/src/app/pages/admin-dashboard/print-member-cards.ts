import { toDataURL } from 'qrcode';

export interface PrintableMember {
  first_name: string;
  last_name: string;
  function_title?: string;
  member_number: string;
  press_card_number?: string;
  photo_file_path?: string;
  qr_token: string;
}

export async function printMemberCards(
  preview: Window,
  members: PrintableMember[],
  verificationBase: string,
  logoUrl: string,
  loadPhoto: (path: string) => Promise<Blob>,
): Promise<void> {
  const doc = preview.document;
  preview.opener = null;
  const photoUrls = new Set<string>();
  preview.addEventListener('pagehide', () => {
    photoUrls.forEach(url => URL.revokeObjectURL(url));
    photoUrls.clear();
  }, { once: true });
  doc.documentElement.lang = 'fr';
  doc.title = 'Cartes des adhérents UNJCI';
  const style = doc.createElement('style');
  style.textContent = `
    * { box-sizing: border-box; }
    body { margin: 1rem; color: #17241f; font: 15px system-ui, sans-serif; }
    header { max-width: 190mm; margin: 0 auto 1rem; }
    button { padding: .55rem 1rem; font: inherit; cursor: pointer; }
    main { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5mm; max-width: 190mm; margin: auto; }
    article { border: 1px solid #bfcfc6; border-top: 3px solid #ec8729; border-radius: 3mm;
      padding: 4mm; break-inside: avoid; overflow-wrap: anywhere; }
    .portrait { display: grid; place-items: center; width: 30mm; height: 30mm;
      margin: 0 auto 3mm; background: #e9f2ed; font-weight: 800; font-size: 1.5rem; }
    .portrait img { width: 100%; height: 100%; object-fit: cover; }
    h2 { margin: 0 0 2mm; font-size: 1rem; }
    article p { margin: 1mm 0; }
    footer { display: flex; justify-content: space-between; align-items: center; margin-top: 3mm; }
    footer img { width: 22mm; height: 22mm; object-fit: contain; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print { header { display: none; } body { margin: 0; } }
    @media screen and (max-width: 580px) { main { grid-template-columns: 1fr; } }
  `;
  doc.head.append(style);
  const header = doc.createElement('header');
  const message = doc.createElement('p');
  message.setAttribute('role', 'status');
  message.textContent = 'Préparation des cartes…';
  const print = doc.createElement('button');
  print.type = 'button';
  print.textContent = 'Imprimer';
  print.disabled = true;
  print.addEventListener('click', () => preview.print());
  header.append(message, print);
  const list = doc.createElement('main');
  doc.body.replaceChildren(header, list);

  async function createCard(member: PrintableMember): Promise<HTMLElement> {
    const card = doc.createElement('article');
    const portrait = doc.createElement('div');
    portrait.className = 'portrait';
    portrait.textContent = `${member.first_name[0] ?? ''}${member.last_name[0] ?? ''}`;
    const name = doc.createElement('h2');
    name.textContent = `${member.last_name.toLocaleUpperCase('fr')} ${member.first_name}`;
    card.append(portrait, name);
    for (const text of [
      member.function_title || 'Journaliste',
      `N° carte CIJP  ${member.press_card_number?.trim() || 'Non renseigné'}`,
      `N° carte UNJCI  ${member.member_number}`,
    ]) {
      const line = doc.createElement('p');
      line.textContent = text;
      card.append(line);
    }
    const footer = doc.createElement('footer');
    const qr = doc.createElement('img');
    qr.alt = 'QR de vérification';
    qr.src = await toDataURL(`${verificationBase}/verification/${encodeURIComponent(member.qr_token)}`, { width: 180 });
    const logo = doc.createElement('img');
    logo.alt = 'UNJCI';
    logo.src = logoUrl;
    footer.append(qr, logo);
    card.append(footer);
    const imageReady = [qr.decode(), logo.decode().catch(() => logo.remove())];
    if (member.photo_file_path) {
      const photo = doc.createElement('img');
      photo.alt = `Photo de ${member.first_name} ${member.last_name}`;
      imageReady.push(loadPhoto(member.photo_file_path).then(async blob => {
        if (preview.closed) return;
        const url = URL.createObjectURL(blob);
        photoUrls.add(url);
        try {
          photo.src = url;
          await photo.decode();
          portrait.replaceChildren(photo);
        } catch {
          URL.revokeObjectURL(url);
          photoUrls.delete(url);
        }
      }).catch(() => undefined));
    }
    await Promise.all(imageReady);
    return card;
  }

  try {
    for (let start = 0; start < members.length && !preview.closed; start += 6) {
      const cards = await Promise.all(members.slice(start, start + 6).map(createCard));
      list.append(...cards);
    }
    if (preview.closed) return;
    message.textContent = `${members.length} ${members.length === 1 ? 'carte prête' : 'cartes prêtes'} à imprimer.`;
    print.disabled = false;
    print.focus();
  } catch (error) {
    message.textContent = 'La préparation des cartes a échoué.';
    throw error;
  }
}
