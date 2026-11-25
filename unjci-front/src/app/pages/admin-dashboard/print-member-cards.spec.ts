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

  it.each([0, 3])('prepares a single common back with %i members and keeps the print formats', async count => {
    const doc = document.implementation.createHTMLDocument();
    const print = vi.fn();
    const preview = { document: doc, opener: {}, closed: false, addEventListener: vi.fn(), print } as unknown as Window;
    const members = Array.from({ length: count }, (_, index) => ({ first_name: 'Awa', last_name: 'Exemple',
      member_number: `TEST-${index}`, qr_token: `test-token-${index}`, photo_file_path: 'members/photos/test.png' }));
    const loadPhoto = vi.fn();
    await printMemberCards(preview, members, 'https://example.test', 'https://example.test/logo.jpg', loadPhoto, 'back');
    expect(doc.querySelectorAll('article.card-back')).toHaveLength(1);
    expect(doc.querySelectorAll('.card-row')).toHaveLength(1);
    for (const card of Array.from(doc.querySelectorAll('article'))) {
      expect(card.querySelector('[lang="fr"]')?.textContent).toContain('toute l’aide et toutes les facilités');
      expect(card.querySelector('[lang="en"]')?.textContent).toContain('every assistance and courtesy');
      expect(card.textContent).toContain('contacts@unjci.ci');
      expect(card.querySelector('.qr')).toBeNull();
    }
    expect(loadPhoto).not.toHaveBeenCalled();
    expect(doc.title).toContain('Verso');
    expect(doc.querySelector('[role="status"]')?.textContent).toContain('1 verso prêt');
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
