import { Directive, ElementRef, Input, OnChanges, OnDestroy, Renderer2, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { AuthService } from './auth.service';
import { environment } from '../../environments/environment';

/** Les fichiers sont chargés avec le jeton, jamais placé dans une URL. */
@Directive({ selector: '[protectedSrc], [protectedHref]', standalone: true })
export class ProtectedFileDirective implements OnChanges, OnDestroy {
  @Input() protectedSrc?: string | null;
  @Input() protectedHref?: string | null;
  private http = inject(HttpClient);
  private auth = inject(AuthService);
  private element = inject(ElementRef);
  private renderer = inject(Renderer2);
  private request?: Subscription;
  private objectUrl?: string;

  ngOnChanges(): void {
    this.clear();
    const path = this.protectedSrc || this.protectedHref;
    const token = this.auth.getSession()?.token;
    if (!path || !token) return;
    const url = new URL(path, window.location.origin);
    const storage = new URL(environment.storageUrl + '/', window.location.origin);
    if (url.origin !== storage.origin || !url.pathname.startsWith(storage.pathname)) return;
    this.request = this.http.get(url.pathname, {
      responseType: 'blob', headers: { Authorization: `Bearer ${token}` },
    }).subscribe({
      next: blob => {
        this.objectUrl = URL.createObjectURL(blob);
        this.renderer.setProperty(this.element.nativeElement, this.protectedSrc ? 'src' : 'href', this.objectUrl);
      },
      error: () => this.clear(),
    });
  }

  ngOnDestroy(): void { this.clear(); }

  private clear(): void {
    this.request?.unsubscribe();
    if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
    this.objectUrl = undefined;
    this.renderer.removeAttribute(this.element.nativeElement, 'src');
    this.renderer.removeAttribute(this.element.nativeElement, 'href');
  }
}
