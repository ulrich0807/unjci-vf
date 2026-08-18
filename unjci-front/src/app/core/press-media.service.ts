import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map, Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';

export type PressMediaType = 'Écrit' | 'Numérique';

export interface PressMediaCatalogItem {
  id: number;
  companyId: number;
  company: string;
  name: string;
  type: PressMediaType;
}

export interface ManagedPressMedia {
  id: number;
  name: string;
  type: PressMediaType;
  isActive: boolean;
}

export interface PressCompanyRecord {
  id: number;
  name: string;
  isActive: boolean;
  media: ManagedPressMedia[];
}

export type AdminPressMedia = ManagedPressMedia;
export type PressCompany = PressCompanyRecord;

export interface PressCompanyPayload {
  name: string;
  isActive?: boolean;
}

export interface PressMediaCreatePayload {
  name: string;
  type: PressMediaType;
  isActive?: boolean;
}

export interface PressMediaUpdatePayload extends PressMediaCreatePayload {
  pressCompanyId: number;
}

interface DataResponse<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class PressMediaService {
  private readonly http = inject(HttpClient);
  private readonly authService = inject(AuthService);
  private readonly apiUrl = environment.apiUrl;

  getCatalog(): Observable<PressMediaCatalogItem[]> {
    return this.http
      .get<DataResponse<PressMediaCatalogItem[]>>(`${this.apiUrl}/press-media`)
      .pipe(map(response => response.data));
  }

  getAdminCompanies(): Observable<PressCompanyRecord[]> {
    return this.http
      .get<DataResponse<PressCompanyRecord[]>>(`${this.apiUrl}/admin/press-companies`, this.authOptions())
      .pipe(map(response => response.data));
  }

  createCompany(payload: PressCompanyPayload): Observable<PressCompanyRecord> {
    return this.http
      .post<DataResponse<PressCompanyRecord>>(`${this.apiUrl}/admin/press-companies`, payload, this.authOptions())
      .pipe(map(response => response.data));
  }

  updateCompany(id: number, payload: PressCompanyPayload): Observable<PressCompanyRecord> {
    return this.http
      .put<DataResponse<PressCompanyRecord>>(`${this.apiUrl}/admin/press-companies/${id}`, payload, this.authOptions())
      .pipe(map(response => response.data));
  }

  deleteCompany(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/press-companies/${id}`, this.authOptions());
  }

  createMedia(companyId: number, payload: PressMediaCreatePayload): Observable<ManagedPressMedia> {
    return this.http
      .post<DataResponse<ManagedPressMedia>>(
        `${this.apiUrl}/admin/press-companies/${companyId}/media`,
        payload,
        this.authOptions(),
      )
      .pipe(map(response => response.data));
  }

  updateMedia(id: number, payload: PressMediaUpdatePayload): Observable<ManagedPressMedia> {
    return this.http
      .put<DataResponse<ManagedPressMedia>>(`${this.apiUrl}/admin/press-media/${id}`, payload, this.authOptions())
      .pipe(map(response => response.data));
  }

  deleteMedia(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/press-media/${id}`, this.authOptions());
  }

  private authOptions(): { headers: HttpHeaders } {
    const token = this.authService.getSession()?.token;
    const headers = token
      ? new HttpHeaders({ Authorization: `Bearer ${token}` })
      : new HttpHeaders();

    return { headers };
  }
}
