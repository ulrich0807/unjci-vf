import { printMemberCards } from './print-member-cards';

describe('Card back printing', () => {
  let originalDecode: PropertyDescriptor | undefined;

  beforeEach(() => {
    originalDecode = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, 'decode');
    Object.defineProperty(HTMLImageElement.prototype, 'decode', { configurable: true, value: vi.fn().mockResolvedValue(undefined) });
  });

  afterEach(() => {
    if (originalDecode) Object.defineProperty(HTMLImageElement.prototype, 'decode', originalDecode);
    else Reflect.deleteProperty(HTMLImageElement.prototype, 'decode');
  });

  it('prepares one bilingual back per member without loading private photos and keeps the print formats', async () => {
    const doc = document.implementation.createHTMLDocument();
    const print = vi.fn();
    const preview = { document: doc, opener: {}, closed: false, addEventListener: vi.fn(), print } as unknown as Window;
    const members = Array.from({ length: 3 }, (_, index) => ({ first_name: 'Awa', last_name: 'Exemple',
      member_number: `TEST-${index}`, qr_token: `test-token-${index}`, photo_file_path: 'members/photos/test.png' }));
    const loadPhoto = vi.fn();
    await printMemberCards(preview, members, 'https://example.test', 'https://example.test/logo.jpg', loadPhoto, 'back');
    expect(doc.querySelectorAll('article.card-back')).toHaveLength(3);
    expect(doc.querySelectorAll('.card-row')).toHaveLength(2);
    for (const card of Array.from(doc.querySelectorAll('article'))) {
      expect(card.querySelector('[lang="fr"]')?.textContent).toContain('toute l’aide et toutes les facilités');
      expect(card.querySelector('[lang="en"]')?.textContent).toContain('every assistance and courtesy');
      expect(card.textContent).toContain('contacts@unjci.ci');
      expect(card.querySelector('.qr')).toBeNull();
    }
    expect(loadPhoto).not.toHaveBeenCalled();
    expect(doc.title).toContain('Versos');
    expect(doc.querySelector('[role="status"]')?.textContent).toContain('3 versos prêts');
    const format = doc.querySelector('select')!;
    expect(format.value).toBe('card');
    format.value = 'a4';
    format.dispatchEvent(new Event('change'));
    expect(doc.querySelector('header')?.textContent).toContain('Papier A4');
    const button = doc.querySelector('button')!;
    expect(button.disabled).toBe(false);
    button.click();
    expect(print).toHaveBeenCalledOnce();
  });
});
