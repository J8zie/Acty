import { Component, signal } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { environment } from '../environments/environment.development';
import { HttpClient } from '@angular/common/http';
@Component({
  imports: [RouterOutlet],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
protected readonly title = signal('frontend');
  constructor(private http: HttpClient) {}
 try(){
   
  this.http.get(`${environment.apiURL}/health`).subscribe({
    next: (response) => console.log(response),
    error: (err) => console.error(err)
  });

}
}